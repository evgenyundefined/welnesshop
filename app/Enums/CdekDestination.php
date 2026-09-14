<?php

namespace App\Enums;

enum CdekDestination: string
{
    case Door = 'door';
    case Point = 'point';

    public function label(): string
    {
        return match ($this) {
            self::Door => 'До двери',
            self::Point => 'В пункт выдачи',
        };
    }

    /**
     * CDEK encodes both ends of the route in delivery_mode: 1 door-door,
     * 2 door-warehouse, 3 warehouse-door, 4 warehouse-warehouse,
     * 6 door-postamat, 7 warehouse-postamat. Only the receiving end matters
     * here, and a parcel locker is a pickup point as far as the buyer is
     * concerned.
     */
    public function matchesMode(int $mode): bool
    {
        return match ($this) {
            self::Door => in_array($mode, [1, 3], true),
            self::Point => in_array($mode, [2, 4, 6, 7], true),
        };
    }
}
