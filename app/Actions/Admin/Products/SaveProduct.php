<?php

namespace App\Actions\Admin\Products;

use App\Models\Product;

class SaveProduct
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes, ?Product $product = null): Product
    {
        $product ??= new Product;

        $product->fill($attributes)->save();

        return $product->load(['category', 'images', 'primaryImage']);
    }
}
