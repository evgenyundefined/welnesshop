<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function isPayable(): bool
    {
        return $this === self::AwaitingPayment;
    }
}
