<?php

namespace webdna\componentlibrary\controllers;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\web\Controller;
use DateTime;
use Throwable;
use webdna\componentlibrary\ComponentLibrary;
use webdna\componentlibrary\models\Component;
use webdna\componentlibrary\models\Story;
use webdna\componentlibrary\services\Renderer;
use yii\base\InvalidArgumentException;
use yii\web\Response;

/**
 * The preview render (§6): `<site base URL>?token=<craft token>&component=&story=&props=`.
 *
 * Anonymous, because the iframe carries no session of its own, and reachable only through a
 * preview token (BR-20). The token row holds the scope, which is rechecked on every request
 * (BR-21). Nothing in the query string picks a site, a path or a scope.
 */
class RenderController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index' => self::ALLOW_ANONYMOUS_LIVE];

    public function actionIndex(): Response
    {
        $this->requireSiteRequest();

        // BR-23, before anything renders: a guest in memory only. logout() and switchIdentity()
        // would both sign the visitor out of this front end. Twig caches its globals once they're
        // read, `currentUser` among them, so they're read again.
        Craft::$app->getUser()->setIdentity(null);
        Craft::$app->getView()->getTwig()->resetGlobals();

        $renderer = ComponentLibrary::getInstance()->getRenderer();
        foreach ($renderer->headers() as $name => $value) {
            $this->response->headers->set($name, $value);
        }

        $scope = $this->scope();
        if ($scope === null || !$renderer->scopeIsValid($scope)) {
            return $this->page(403, $renderer->errorPage(Craft::t('component-library', 'Preview expired, reload the page.')));
        }

        $component = $this->component();
        $story = $component === null ? null : $this->story($component);
        if ($component === null || $story === null) {
            return $this->page(404, $renderer->errorPage(Craft::t('component-library', 'There’s no such component or example.')));
        }

        try {
            $props = $renderer->props($component, $story, $renderer->decodeProps($this->request->getQueryParam('props')));
        } catch (InvalidArgumentException $e) {
            return $this->page(400, $renderer->errorPage($e->getMessage()));
        }

        try {
            return $this->page(200, $renderer->render($component, $story, $props));
        } catch (Throwable $e) {
            Craft::warning(sprintf('Preview of %s “%s” failed: %s', $component->handle, $story->name, $e->getMessage()), __METHOD__);

            // BR-26: detail for the team only, never on a share link.
            return $this->page(500, Renderer::isUserScope($scope)
                ? $renderer->errorPage(Craft::t('component-library', 'This component couldn’t be shown.'), $renderer->describe($e))
                : $renderer->errorPage(Craft::t('component-library', 'This component couldn’t be shown.')));
        }
    }

    /**
     * The scope from the token's own row, never from a request parameter (TN-4).
     *
     * Craft refuses an unknown or expired token before routing (TN-1), but only deletes expired
     * rows once per process, so the row is read here with its expiry rather than trusted.
     */
    private function scope(): ?string
    {
        $token = $this->request->getToken();
        if ($token === null) {
            return null;
        }

        $route = Json::decodeIfJson((string)(new Query())
            ->select(['route'])
            ->from(Table::TOKENS)
            ->where(['token' => $token])
            ->andWhere(['>', 'expiryDate', Db::prepareDateForDb(new DateTime())])
            ->scalar());

        if (!is_array($route) || ($route[0] ?? null) !== Renderer::ROUTE) {
            return null;
        }

        $scope = $route[1]['scope'] ?? null;

        return is_string($scope) ? $scope : null;
    }

    /** A handle is only ever an index key (BR-22, BR-24). */
    private function component(): ?Component
    {
        $handle = $this->request->getQueryParam('component');
        if (!is_string($handle) || !preg_match(Component::HANDLE_PATTERN, $handle)) {
            return null;
        }

        return ComponentLibrary::getInstance()->getIndex()->get($handle);
    }

    /** The named story, or the first when none is named. */
    private function story(Component $component): ?Story
    {
        $name = $this->request->getQueryParam('story');
        if ($name === null || $name === '') {
            return $component->stories[array_key_first($component->stories)] ?? null;
        }

        return is_string($name) ? ($component->stories[$name] ?? null) : null;
    }

    private function page(int $status, string $html): Response
    {
        $this->response->setStatusCode($status);
        $this->response->format = Response::FORMAT_RAW;
        $this->response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $this->response->data = $html;

        return $this->response;
    }
}
