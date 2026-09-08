<?php

namespace App\Actions\Cart;

use App\Models\Cart;

class ClearCart
{
    public function __invoke(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
