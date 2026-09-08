<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Sbp = 'sbp';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Банковская карта',
            self::Sbp => 'СБП',
            self::Invoice => 'Счёт для юридических лиц',
        };
    }
}
