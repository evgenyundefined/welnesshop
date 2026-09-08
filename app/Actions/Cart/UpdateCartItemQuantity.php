<?php

namespace App\Actions\Cart;

use App\Exceptions\CartItemNotFound;
use App\Exceptions\ProductNotAvailable;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Config\Repository as Config;

class UpdateCartItemQuantity
{
    public function __construct(private readonly Config $config) {}

    /**
     * @throws CartItemNotFound
     * @throws ProductNotAvailable
     */
    public function __invoke(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        if ($item->cart_id !== $cart->id) {
            throw new CartItemNotFound;
        }

        $product = $item->product;
        $withinStock = $quantity <= $product->stock;
        $withinLimit = $quantity <= $this->config->integer('shop.max_item_quantity');

        if (! $product->status->isVisibleInCatalog() || ! $withinStock || ! $withinLimit) {
            throw ProductNotAvailable::forProduct($product);
        }

        $item->update(['quantity' => $quantity]);

        return $item;
    }
}
