<?php

namespace App\Actions\Orders;

use App\Exceptions\CartIsEmpty;
use App\Exceptions\ProductNotAvailable;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;

class PlaceOrder
{
    public function __construct(private readonly RecordOrder $recordOrder) {}

    /**
     * @param  array<string, mixed>  $details
     *
     * @throws CartIsEmpty
     * @throws ProductNotAvailable
     * @throws \Throwable
     */
    public function __invoke(Customer $customer, Cart $cart, array $details): Order
    {
        if ($cart->items->isEmpty()) {
            throw new CartIsEmpty;
        }

        $order = ($this->recordOrder)(
            $customer,
            $cart->items->pluck('quantity', 'product_id')->all(),
            $details,
        );

        $cart->items()->delete();

        return $order;
    }
}
