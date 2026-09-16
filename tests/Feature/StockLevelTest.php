<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\StockLevel;
use App\Models\Admin;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StockLevelTest extends TestCase
{
    /** Distinctive on purpose: nothing else in the payload can spell it. */
    private const SECRET_STOCK = 4242;

    #[DataProvider('quantities')]
    public function test_a_quantity_becomes_a_level(int $stock, StockLevel $expected): void
    {
        $this->assertSame($expected, StockLevel::fromQuantity($stock));
        $this->assertSame($expected, $this->makeProduct(['stock' => $stock])->stockLevel());
    }

    /** @return array<string, array{int, StockLevel}> */
    public static function quantities(): array
    {
        return [
            'nothing left' => [0, StockLevel::OutOfStock],
            'the last one' => [1, StockLevel::Last],
            'two is few' => [2, StockLevel::Few],
            'ten is still few' => [10, StockLevel::Few],
            'eleven is enough' => [11, StockLevel::Enough],
            'twenty is enough' => [20, StockLevel::Enough],
            'twenty one is many' => [21, StockLevel::Many],
            'a warehouse is many' => [5000, StockLevel::Many],
        ];
    }

    #[DataProvider('labels')]
    public function test_the_catalog_says_it_in_words(int $stock, string $label): void
    {
        $product = $this->makeProduct(['stock' => $stock, 'status' => ProductStatus::Published]);

        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.stock_label', $label);
    }

    /** @return array<string, array{int, string}> */
    public static function labels(): array
    {
        return [
            'nothing left' => [0, 'Нет в наличии'],
            'the last one' => [1, 'Последний'],
            'few' => [7, 'Мало'],
            'enough' => [15, 'Достаточно'],
            'many' => [120, 'Много'],
        ];
    }

    public function test_the_catalog_never_hands_out_the_exact_count(): void
    {
        // Every other number is pinned, so the count is the only place this
        // one could come from. Left to the factory, a random price of 37465710
        // contains it and fails the search on its own — as it once did.
        $product = $this->makeProduct([
            'stock' => self::SECRET_STOCK,
            'status' => ProductStatus::Published,
            'price_minor' => 1_000_00,
            'weight_grams' => 500,
        ]);

        // The count must not reach the browser by any name — not as stock, not
        // inside the cart, not through the listing that feeds the catalog grid.
        $this->getJson("/api/products/{$product->slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.stock')
            ->assertJsonPath('data.stock_level', StockLevel::Many->value)
            ->assertDontSee((string) self::SECRET_STOCK, false);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonMissingPath('data.0.stock')
            ->assertDontSee((string) self::SECRET_STOCK, false);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.product.stock')
            ->assertDontSee((string) self::SECRET_STOCK, false);
    }

    public function test_the_admin_still_sees_the_exact_count(): void
    {
        $product = $this->makeProduct(['stock' => 37]);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->getJson("/admin/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.stock', 37);
    }

    public function test_the_storefront_learns_the_limit_it_can_no_longer_derive(): void
    {
        $this->app['config']->set('shop.max_item_quantity', 7);

        $this->getJson('/api/site')->assertOk()->assertJsonPath('data.max_item_quantity', 7);
    }

    public function test_a_quantity_above_the_stock_is_still_refused(): void
    {
        $product = $this->makeProduct(['stock' => 3, 'status' => ProductStatus::Published]);

        // The browser no longer knows the stock, so the refusal has to come
        // from the server and has to say something the buyer can act on.
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 4])
            ->assertStatus(422)
            ->assertJsonPath('message', "Товар «{$product->name}» недоступен в запрошенном количестве.");

        $this->assertDatabaseCount('cart_items', 0);
    }
}
