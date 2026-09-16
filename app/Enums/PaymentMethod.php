<?php

namespace App\Enums;

/**
 * Declaration order is the order the checkout offers them in, and the first
 * one the form lands on. It is not alphabetical and not historical: it is what
 * the shop would rather the buyer picked.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case Sbp = 'sbp';
    case OnAgreement = 'on_agreement';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Банковская карта',
            self::Sbp => 'СБП',
            self::Invoice => 'Счёт для юридических лиц',
            self::OnAgreement => 'По согласованию с менеджером',
        };
    }

    /**
     * Whether the money is taken by the payment gateway. With the gateway
     * switched off these are not offered: a buyer picking a card and then
     * finding no way to pay has been misled.
     */
    public function isOnline(): bool
    {
        return $this === self::Card || $this === self::Sbp;
    }
}
