<?php

namespace App\Actions\Cart;

use App\Exceptions\CartItemNotFound;
use App\Models\Cart;
use App\Models\CartItem;

class RemoveCartItem
{
    /**
     * @throws CartItemNotFound
     */
    public function __invoke(Cart $cart, CartItem $item): void
    {
        if ($item->cart_id !== $cart->id) {
            throw new CartItemNotFound;
        }

        $item->delete();
    }
}
