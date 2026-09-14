<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case Courier = 'courier';
    case Cdek = 'cdek';

    public function label(): string
    {
        return match ($this) {
            self::Courier => 'Курьером',
            self::Cdek => 'СДЭК',
        };
    }

    /**
     * A courier order is priced by hand; a CDEK one carries the cost the
     * carrier quoted, along with the city, tariff and pickup point it was
     * quoted for.
     */
    public function isCarrier(): bool
    {
        return $this === self::Cdek;
    }
}
