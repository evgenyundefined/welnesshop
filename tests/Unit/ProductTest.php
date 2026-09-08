<?php

namespace Tests\Unit;

use App\Enums\ProductStatus;
use App\Models\Product;
use Tests\TestCase;

class ProductTest extends TestCase
{
    public function test_a_published_product_with_stock_is_available(): void
    {
        $this->assertTrue($this->makeProduct(['stock' => 1])->isAvailable());
    }

    public function test_a_published_product_without_stock_is_not_available(): void
    {
        $this->assertFalse($this->makeProduct(['stock' => 0])->isAvailable());
    }

    public function test_an_unpublished_product_is_unavailable_however_much_stock_it_has(): void
    {
        $this->assertFalse($this->makeProduct(['stock' => 50, 'status' => ProductStatus::Draft])->isAvailable());
        $this->assertFalse($this->makeProduct(['stock' => 50, 'status' => ProductStatus::Archived])->isAvailable());
    }

    public function test_the_published_scope_keeps_only_published_products(): void
    {
        $published = $this->makeProduct();
        $this->makeProduct(['status' => ProductStatus::Draft]);
        $this->makeProduct(['status' => ProductStatus::Archived]);

        $this->assertSame([$published->id], Product::query()->published()->pluck('id')->all());
    }

    public function test_a_product_is_addressed_by_its_slug(): void
    {
        $product = $this->makeProduct();

        $this->assertSame('slug', $product->getRouteKeyName());
        $this->assertSame($product->slug, $product->getRouteKey());
    }
}
