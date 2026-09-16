<?php

namespace App\Actions\Orders;

use App\Exceptions\BelowMinimumQuantity;
use App\Exceptions\CartIsEmpty;
use App\Exceptions\ProductNotAvailable;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;

class PlaceOrder
{
    public function __construct(private readonly RecordOrder $recordOrder) {}

    /**
     * @param  array<string, mixed>  $details
     *
     * @throws BelowMinimumQuantity
     * @throws CartIsEmpty
     * @throws ProductNotAvailable
     * @throws \Throwable
     */
    public function __invoke(Customer $customer, Cart $cart, array $details): Order
    {
        if ($cart->items->isEmpty()) {
            throw new CartIsEmpty;
        }

        $this->assertWholesaleLots($cart);

        $order = ($this->recordOrder)(
            $customer,
            $cart->items->pluck('quantity', 'product_id')->all(),
            $details,
        );

        $cart->items()->delete();

        return $order;
    }

    /**
     * Checked again here and not only when the line was added: a cart can be
     * days old, and the shop can raise a category's minimum in between. This
     * is the storefront's gate, so an administrator entering an order by hand
     * is not bound by it.
     *
     * @throws BelowMinimumQuantity
     */
    private function assertWholesaleLots(Cart $cart): void
    {
        $short = $cart->items->first(
            static fn (CartItem $item): bool => $item->quantity < $item->product->minOrderQuantity(),
        );

        if ($short !== null) {
            throw BelowMinimumQuantity::forProduct($short->product);
        }
    }
}
