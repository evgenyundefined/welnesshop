<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum StatisticsPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case All = 'all';

    public function days(): ?int
    {
        return match ($this) {
            self::Week => 7,
            self::Month => 30,
            self::Quarter => 90,
            self::All => null,
        };
    }

    /**
     * The first moment the period covers, or null when it covers everything.
     */
    public function since(CarbonImmutable $now): ?CarbonImmutable
    {
        $days = $this->days();

        return $days === null ? null : $now->subDays($days - 1)->startOfDay();
    }

    public function label(): string
    {
        return match ($this) {
            self::Week => '7 дней',
            self::Month => '30 дней',
            self::Quarter => '90 дней',
            self::All => 'Всё время',
        };
    }
}
