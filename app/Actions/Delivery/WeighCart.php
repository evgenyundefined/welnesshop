<?php

namespace App\Actions\Delivery;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Config\Repository as Config;

class WeighCart
{
    public function __construct(private readonly Config $config) {}

    /**
     * A product without a weight falls back to the configured one, and the
     * parcel never weighs less than that: the carrier prices a parcel, and a
     * weightless one would be refused.
     */
    public function __invoke(Cart $cart): int
    {
        $fallback = $this->config->integer('shop.default_product_weight_grams');

        $weight = $cart->items->sum(
            static fn (CartItem $item): int => ($item->product->weight_grams ?? $fallback) * $item->quantity,
        );

        return max($fallback, (int) $weight);
    }
}
