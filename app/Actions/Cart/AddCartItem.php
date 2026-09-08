<?php

namespace App\Actions\Cart;

use App\Exceptions\ProductNotAvailable;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Config\Repository as Config;

class AddCartItem
{
    public function __construct(private readonly Config $config) {}

    /**
     * @throws ProductNotAvailable
     */
    public function __invoke(Cart $cart, Product $product, int $quantity): CartItem
    {
        /** @var CartItem $item */
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $requested = $item->quantity + $quantity;

        $this->assertAvailable($product, $requested);

        $item->quantity = $requested;
        $item->save();

        return $item;
    }

    /**
     * @throws ProductNotAvailable
     */
    private function assertAvailable(Product $product, int $requested): void
    {
        $withinStock = $requested <= $product->stock;
        $withinLimit = $requested <= $this->config->integer('shop.max_item_quantity');

        if (! $product->status->isVisibleInCatalog() || ! $withinStock || ! $withinLimit) {
            throw ProductNotAvailable::forProduct($product);
        }
    }
}
