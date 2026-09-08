<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case Courier = 'courier';
    case TransportCompany = 'transport_company';

    public function label(): string
    {
        return match ($this) {
            self::Courier => 'Курьером',
            self::TransportCompany => 'Транспортной компанией',
        };
    }
}
