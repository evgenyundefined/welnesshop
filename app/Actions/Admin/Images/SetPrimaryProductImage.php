<?php

namespace App\Actions\Admin\Images;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;

class SetPrimaryProductImage
{
    /**
     * @throws \Throwable
     */
    public function __invoke(Product $product, ProductImage $image): ProductImage
    {
        DB::transaction(function () use ($product, $image): void {
            $product->images()->whereKeyNot($image->getKey())->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return $image;
    }
}
