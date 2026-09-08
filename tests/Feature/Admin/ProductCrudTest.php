<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAdmin();
    }

    public function test_the_listing_shows_every_status_unlike_the_storefront(): void
    {
        $published = $this->makeProduct(['name' => 'Aaa', 'status' => ProductStatus::Published]);
        $draft = $this->makeProduct(['name' => 'Bbb', 'status' => ProductStatus::Draft]);
        $archived = $this->makeProduct(['name' => 'Ccc', 'status' => ProductStatus::Archived]);

        $this->getJson(route('admin.api.products.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.1.id', $draft->id)
            ->assertJsonPath('data.2.id', $archived->id);

        $this->getJson(route('api.products'))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_listing_filters_by_category_status_and_search(): void
    {
        $category = Category::factory()->create();
        $wanted = $this->makeProduct([
            'category_id' => $category->id,
            'name' => 'Эпиталон',
            'slug' => 'epitalon',
            'status' => ProductStatus::Draft,
        ]);
        $this->makeProduct(['category_id' => $category->id, 'name' => 'Тимозин', 'status' => ProductStatus::Published]);
        $this->makeProduct(['name' => 'Эпиталон плюс', 'status' => ProductStatus::Draft]);

        $this->getJson(route('admin.api.products.index', ['category_id' => $category->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson(route('admin.api.products.index', [
            'category_id' => $category->id,
            'status' => ProductStatus::Draft->value,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);

        $this->getJson(route('admin.api.products.index', ['search' => 'epitalon']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id);
    }

    public function test_the_listing_rejects_an_unknown_filter_value(): void
    {
        $this->getJson(route('admin.api.products.index', ['status' => 'sold']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson(route('admin.api.products.index', ['category_id' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $this->getJson(route('admin.api.products.index', ['per_page' => 10_000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_the_listing_loads_categories_without_querying_per_row(): void
    {
        $this->makeProduct();
        $this->makeProduct();
        $this->makeProduct();

        DB::enableQueryLog();

        $this->getJson(route('admin.api.products.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.category.id', fn (int $id): bool => $id > 0);

        // Rows, count, categories and covers: four queries whatever the page size.
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));

        DB::disableQueryLog();
    }

    public function test_an_admin_can_create_a_product(): void
    {
        $category = Category::factory()->create();

        $this->postJson(route('admin.api.products.store'), [
            'category_id' => $category->id,
            'name' => '  Эпиталон 10 мг  ',
            'summary' => 'Пептид эпифиза',
            'maturity' => 'Готов к отгрузке',
            'supplier' => 'Поставщик',
            'source_url' => 'https://example.com/epitalon',
            'status' => ProductStatus::Published->value,
            'price_minor' => 120_000,
            'stock' => 12,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Эпиталон 10 мг')
            ->assertJsonPath('data.slug', 'epitalon-10-mg')
            ->assertJsonPath('data.currency', config('shop.currency'))
            ->assertJsonPath('data.price_minor', 120_000)
            ->assertJsonPath('data.stock', 12)
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertDatabaseHas('products', [
            'slug' => 'epitalon-10-mg',
            'category_id' => $category->id,
            'status' => ProductStatus::Published->value,
            'stock' => 12,
        ]);
    }

    public function test_a_created_product_is_immediately_visible_in_the_storefront(): void
    {
        $category = Category::factory()->create();

        $this->postJson(route('admin.api.products.store'), [
            'category_id' => $category->id,
            'name' => 'Новинка',
            'status' => ProductStatus::Published->value,
            'price_minor' => 50_000,
            'stock' => 4,
        ])->assertCreated();

        $this->getJson(route('api.products.show', ['product' => 'novinka']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Новинка');
    }

    public function test_a_draft_product_stays_hidden_from_the_storefront(): void
    {
        $category = Category::factory()->create();

        $this->postJson(route('admin.api.products.store'), [
            'category_id' => $category->id,
            'name' => 'Черновик',
            'status' => ProductStatus::Draft->value,
            'price_minor' => 50_000,
            'stock' => 4,
        ])->assertCreated();

        $this->getJson(route('api.products.show', ['product' => 'chernovik']))->assertNotFound();
    }

    public function test_a_derived_slug_that_collides_is_rejected_by_validation(): void
    {
        $this->makeProduct(['slug' => 'epitalon']);
        $category = Category::factory()->create();

        $this->postJson(route('admin.api.products.store'), [
            'category_id' => $category->id,
            'name' => 'Epitalon',
            'status' => ProductStatus::Published->value,
            'price_minor' => 1_000,
            'stock' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame(1, Product::query()->count());
    }

    public function test_creation_rejects_a_missing_category_and_a_free_price(): void
    {
        $this->postJson(route('admin.api.products.store'), [
            'category_id' => 999,
            'name' => 'Товар',
            'status' => ProductStatus::Published->value,
            'price_minor' => 0,
            'stock' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'price_minor', 'stock']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_an_admin_can_update_a_product(): void
    {
        $product = $this->makeProduct(['slug' => 'epitalon', 'name' => 'Эпиталон', 'stock' => 3]);
        $category = Category::factory()->create();

        $this->putJson(route('admin.api.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Эпиталон 20 мг',
            'slug' => 'epitalon',
            'status' => ProductStatus::Archived->value,
            'price_minor' => 250_000,
            'currency' => 'usd',
            'stock' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Эпиталон 20 мг')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $category->id,
            'status' => ProductStatus::Archived->value,
            'price_minor' => 250_000,
            'stock' => 0,
        ]);
    }

    public function test_an_update_cannot_steal_another_products_slug(): void
    {
        $this->makeProduct(['slug' => 'thymosin']);
        $product = $this->makeProduct(['slug' => 'epitalon']);

        $this->putJson(route('admin.api.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => 'Эпиталон',
            'slug' => 'thymosin',
            'status' => ProductStatus::Published->value,
            'price_minor' => 1_000,
            'stock' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame('epitalon', $product->refresh()->slug);
    }

    public function test_an_admin_can_delete_a_product_that_was_never_ordered(): void
    {
        $product = $this->makeProduct();

        $this->deleteJson(route('admin.api.products.destroy', $product))->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_a_product_that_appears_in_an_order_cannot_be_deleted(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $this->makeOrder(Customer::factory()->create(), [$product->id => 1]);

        $this->deleteJson(route('admin.api.products.destroy', $product))
            ->assertConflict()
            ->assertJsonPath('message', 'Товар уже есть в заказах — его можно только перевести в архив.');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_an_unknown_product_is_a_not_found(): void
    {
        $this->getJson(route('admin.api.products.show', ['product' => 999]))->assertNotFound();
    }
}
