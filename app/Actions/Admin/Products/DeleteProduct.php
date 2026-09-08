<?php

namespace App\Actions\Admin\Products;

use App\Exceptions\ProductIsOrdered;
use App\Models\Product;

class DeleteProduct
{
    /**
     * @throws ProductIsOrdered
     */
    public function __invoke(Product $product): void
    {
        if ($product->orderItems()->exists()) {
            throw new ProductIsOrdered;
        }

        $product->delete();
    }
}
