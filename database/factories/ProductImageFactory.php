<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'products/'.Str::lower(Str::random(8)).'.webp',
            'position' => 0,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }
}
