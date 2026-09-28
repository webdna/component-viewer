<?php

namespace webdna\componentlibrary\models;

use Craft;
use craft\base\Model;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The create form for a share link (BR-28). Dates are calendar days in UTC, and a link lasts to
 * the end of its chosen day (spec §4).
 */
class ShareForm extends Model
{
    public const LABEL_MAX = 100;
    public const DEFAULT_DAYS = 14;
    public const MAX_DAYS = 90;
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/D';

    public string $label = '';

    /** `Y-m-d`, as a date input posts it. */
    public string $expiry = '';

    /** A new form as the list page shows it, two weeks out (BR-28). A post never gets this default. */
    public static function blank(): self
    {
        return new self(['expiry' => self::day(self::DEFAULT_DAYS)]);
    }

    public function attributeLabels(): array
    {
        return [
            'label' => Craft::t('component-library', 'Label'),
            'expiry' => Craft::t('component-library', 'Expires'),
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['label', 'expiry'], 'trim'],
            [['label', 'expiry'], 'required'],
            ['label', 'string', 'max' => self::LABEL_MAX],
            ['expiry', 'validateExpiry'],
        ];
    }

    public function validateExpiry(string $attribute): void
    {
        $min = self::day(1);
        $max = self::day(self::MAX_DAYS);
        $value = $this->$attribute;

        // Both bounds are Y-m-d, so a string comparison is a date comparison once the shape is checked.
        if (!self::isDate($value) || $value < $min || $value > $max) {
            $this->addError($attribute, Craft::t('component-library', 'Choose a date from {min} to {max}.', [
                'min' => $min,
                'max' => $max,
            ]));
        }
    }

    /** The last second of the chosen day, UTC: what `expiresAt` holds. */
    public function expiresAt(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->expiry . ' 23:59:59', new DateTimeZone('UTC'));
    }

    /** Today plus `$days`, as `Y-m-d` in UTC. */
    public static function day(int $days): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone('UTC')))->modify("+$days days")->format('Y-m-d');
    }

    private static function isDate(string $value): bool
    {
        if (!preg_match(self::DATE_PATTERN, $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}
