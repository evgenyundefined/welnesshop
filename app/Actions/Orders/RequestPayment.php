<?php

namespace App\Actions\Orders;

use App\Exceptions\OrderNotPayable;
use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Payments\PaymentIntent;

class RequestPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyPaymentResult $applyPaymentResult,
    ) {}

    /**
     * @throws OrderNotPayable
     */
    public function __invoke(Order $order): PaymentIntent
    {
        if (! $order->status->isPayable()) {
            throw new OrderNotPayable;
        }

        $intent = $this->gateway->createPayment($order);

        ($this->applyPaymentResult)($order, $intent);

        return $intent;
    }
}
