# Resolver

The resolver finds a file across the library's folders, site versions included, for PHP code that
renders templates of its own. mw-core's Handlebars cards are one example. It applies the same
precedence as a Twig `@handle` include, so a site's version of a file replaces the shared one on
that site only.

It's a stable public API: its signature and behaviour won't change within v2.

```php
use webdna\componentlibrary\ComponentLibrary;

$resolver = ComponentLibrary::getInstance()->getResolver();

$path = $resolver->resolve('cards/price.hbs');          // the current site
$path = $resolver->resolve('cards/price.hbs', 'uk');    // the uk site's version, if it has one
```

## `resolve(string $path, ?string $siteHandle = null): ?string`

Returns the absolute path of `$path` in the highest-precedence folder that has it, or `null`.

- **`$path`** is relative to a folder in `templateDirectories` (or to a site's folder), with its
  extension: `cards/price.hbs`, not `@cards:price`. Any file type works, not only Twig.
- **`$siteHandle`** chooses whose site versions are searched. `null` means Craft's current site.

Folders are searched in reverse precedence order, so the first match wins:

1. `<sites>/<site handle>`, if `sites` is set in `config/component-library.php`
2. The last folder in `templateDirectories`
3. …back to the first

With this config:

```php
return [
    'templateDirectories' => ['@templates/_base'],
    'sites' => '@templates/_sites',
];
```

`resolve('cards/price.hbs', 'uk')` returns `templates/_sites/uk/cards/price.hbs` if that file
exists, and otherwise `templates/_base/cards/price.hbs`, or `null` if neither does.

## What returns `null`

The resolver is safe to call with a value from a request. It never returns a path outside the
library's folders, and never another site's version:

| Input | Result |
|---|---|
| A path that exists in no folder | `null` |
| An empty path, or one containing `..` or a NUL byte | `null` |
| A path starting with `/` | `null` |
| A site handle Craft doesn't know | `null`. It doesn't fall back to the current site. |
| `_sites/de/cards/price.hbs` through a shared folder that contains the sites folder | Not found there. Only the chosen site's own folder is searched. |

Only the handle of a site Craft returns becomes part of a path, never the string passed in.

## Migrating from a hand-built lookup

Code that joins the site folder and the shared folder itself and checks each in turn, as mw-core's
`HandlebarsService::resolve()` does, can be replaced with one call. That puts it on the plugin's
config, so the two can't disagree about which folders exist. It also stops a `$site` or `$name`
from a caller becoming a path segment unchecked:

```php
// before
$site ??= Craft::$app->getSites()->getCurrentSite()->handle;
foreach (["$root/_sites/$site/$name.hbs", "$root/_base/$name.hbs"] as $path) {
    if (is_file($path)) {
        return $path;
    }
}
return null;

// after
return ComponentLibrary::getInstance()->getResolver()->resolve("$name.hbs", $site);
```
