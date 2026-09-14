<?php

namespace App\Payments;

use App\Models\Order;

interface PaymentGateway
{
    public function createPayment(Order $order): PaymentIntent;

    /**
     * Whether money can actually be taken. The storefront says so on the
     * payment page instead of offering a button that leads nowhere.
     */
    public function isLive(): bool;
}
