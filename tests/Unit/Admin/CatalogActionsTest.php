<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Categories\DeleteCategory;
use App\Actions\Admin\Categories\ListCategories;
use App\Actions\Admin\Categories\SaveCategory;
use App\Actions\Admin\Products\DeleteProduct;
use App\Actions\Admin\Products\ListProducts;
use App\Actions\Admin\Products\SaveProduct;
use App\Enums\ProductStatus;
use App\Exceptions\CategoryHasProducts;
use App\Exceptions\ProductIsOrdered;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Tests\TestCase;

class CatalogActionsTest extends TestCase
{
    public function test_saving_a_category_creates_then_updates_the_same_row(): void
    {
        $save = $this->app->make(SaveCategory::class);

        $created = $save(['slug' => 'peptidy', 'name' => 'Пептиды', 'description' => null, 'position' => 2]);

        $this->assertTrue($created->exists);
        $this->assertSame(0, $created->products_count);
        $this->assertDatabaseCount('categories', 1);

        $updated = $save(
            ['slug' => 'peptidy', 'name' => 'Пептиды и биорегуляторы', 'description' => 'Описание', 'position' => 5],
            $created,
        );

        $this->assertSame($created->id, $updated->id);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('categories', ['id' => $created->id, 'position' => 5, 'description' => 'Описание']);
    }

    public function test_an_empty_category_is_deleted_and_a_populated_one_is_refused(): void
    {
        $delete = $this->app->make(DeleteCategory::class);

        $empty = Category::factory()->create();
        $delete($empty);
        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);

        $populated = Category::factory()->create();
        $product = $this->makeProduct(['category_id' => $populated->id]);

        $this->expectException(CategoryHasProducts::class);

        try {
            $delete($populated);
        } finally {
            $this->assertDatabaseHas('categories', ['id' => $populated->id]);
            $this->assertDatabaseHas('products', ['id' => $product->id]);
        }
    }

    public function test_the_category_listing_is_ordered_by_position_then_name(): void
    {
        $third = Category::factory()->create(['position' => 5, 'name' => 'Аминокислоты']);
        $second = Category::factory()->create(['position' => 1, 'name' => 'Пептиды']);
        $first = Category::factory()->create(['position' => 1, 'name' => 'Витамины']);

        $ids = ($this->app->make(ListCategories::class))(null, null)->pluck('id')->all();

        $this->assertSame([$first->id, $second->id, $third->id], $ids);
    }

    public function test_the_category_listing_searches_by_name_only(): void
    {
        $match = Category::factory()->create(['name' => 'Пептиды', 'slug' => 'peptidy']);
        Category::factory()->create(['name' => 'Витамины', 'slug' => 'peptidy-like']);

        $list = $this->app->make(ListCategories::class);

        $this->assertSame([$match->id], $list('Пепт', null)->pluck('id')->all());
        $this->assertCount(0, $list('peptidy', null)->items());
    }

    public function test_saving_a_product_creates_then_updates_the_same_row(): void
    {
        $save = $this->app->make(SaveProduct::class);
        $category = Category::factory()->create();

        $attributes = [
            'category_id' => $category->id,
            'slug' => 'epitalon',
            'name' => 'Эпиталон',
            'summary' => null,
            'maturity' => null,
            'supplier' => null,
            'source_url' => null,
            'status' => ProductStatus::Published,
            'price_minor' => 120_000,
            'currency' => 'RUB',
            'stock' => 4,
        ];

        $created = $save($attributes);

        $this->assertTrue($created->relationLoaded('category'));
        $this->assertSame($category->id, $created->category->id);

        $updated = $save([...$attributes, 'stock' => 0, 'status' => ProductStatus::Archived], $created);

        $this->assertSame($created->id, $updated->id);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', [
            'id' => $created->id,
            'stock' => 0,
            'status' => ProductStatus::Archived->value,
        ]);
    }

    public function test_an_unordered_product_is_deleted_and_an_ordered_one_is_refused(): void
    {
        $delete = $this->app->make(DeleteProduct::class);

        $unordered = $this->makeProduct();
        $delete($unordered);
        $this->assertDatabaseMissing('products', ['id' => $unordered->id]);

        $ordered = $this->makeProduct(['stock' => 5]);
        $this->makeOrder(Customer::factory()->create(), [$ordered->id => 1]);

        $this->expectException(ProductIsOrdered::class);

        try {
            $delete($ordered);
        } finally {
            $this->assertDatabaseHas('products', ['id' => $ordered->id]);
        }
    }

    public function test_the_product_listing_covers_every_status_and_filters_narrow_it(): void
    {
        $list = $this->app->make(ListProducts::class);
        $category = Category::factory()->create();

        $draft = $this->makeProduct(['category_id' => $category->id, 'name' => 'Aaa', 'status' => ProductStatus::Draft]);
        $published = $this->makeProduct(['category_id' => $category->id, 'name' => 'Bbb']);
        $elsewhere = $this->makeProduct(['name' => 'Ccc', 'status' => ProductStatus::Archived]);

        $this->assertSame(
            [$draft->id, $published->id, $elsewhere->id],
            $list(null, null, null, null)->pluck('id')->all(),
        );
        $this->assertSame([$draft->id, $published->id], $list(null, $category->id, null, null)->pluck('id')->all());
        $this->assertSame([$draft->id], $list(null, $category->id, ProductStatus::Draft, null)->pluck('id')->all());
        $this->assertSame([$elsewhere->id], $list('Ccc', null, null, null)->pluck('id')->all());
    }

    public function test_the_product_listing_searches_by_slug_too(): void
    {
        $match = $this->makeProduct(['name' => 'Эпиталон', 'slug' => 'epitalon-10']);
        $this->makeProduct(['name' => 'Тимозин', 'slug' => 'thymosin']);

        $found = ($this->app->make(ListProducts::class))('epitalon', null, null, null);

        $this->assertSame([$match->id], $found->pluck('id')->all());
    }

    public function test_the_product_listing_eager_loads_the_category(): void
    {
        $this->makeProduct();

        $product = ($this->app->make(ListProducts::class))(null, null, null, null)->first();

        $this->assertInstanceOf(Product::class, $product);
        $this->assertTrue($product->relationLoaded('category'));
    }
}
