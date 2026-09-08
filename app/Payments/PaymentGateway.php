<?php

namespace App\Payments;

use App\Models\Order;

interface PaymentGateway
{
    public function createPayment(Order $order): PaymentIntent;
}
