<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::ucfirst(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'summary' => fake()->sentence(),
            'maturity' => fake()->sentence(3),
            'supplier' => fake()->company(),
            'source_url' => fake()->url(),
            'status' => ProductStatus::Published,
            'price_minor' => fake()->numberBetween(100_00, 900_000_00),
            'currency' => config('shop.currency'),
            'stock' => fake()->numberBetween(1, 50),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ProductStatus::Archived]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }
}
