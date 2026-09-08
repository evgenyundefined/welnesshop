<?php

namespace App\Actions\Orders;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShowOrder
{
    /**
     * @throws ModelNotFoundException<Order>
     */
    public function __invoke(Customer $customer, Order $order): Order
    {
        if ($order->customer_id !== $customer->id) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return $order->load('items');
    }
}
