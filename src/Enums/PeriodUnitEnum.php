<?php

namespace Wowmaking\WebPurchases\Enums;

final class PeriodUnitEnum
{
    public const DAY = 'D';
    public const WEEK = 'W';
    public const MONTH = 'M';
    public const YEAR = 'Y';

    public static function list(): array
    {
        return [
            self::DAY,
            self::WEEK,
            self::MONTH,
            self::YEAR,
        ];
    }
}
