<?php

namespace Tests\Unit;

use App\Enums\Currency;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    /** @var array<string, array{0: int, 1: int}> */
    private const array PRICE_RANGES_MINOR = [
        'wellness' => [500_000, 25_000_000],
        'peptidy' => [300_000, 6_000_000],
        'vitaminy-i-dobavki' => [150_000, 900_000],
        'beauty' => [250_000, 1_500_000],
        'inektsionnye-preparaty' => [600_000, 9_000_000],
        'ingalyatory' => [800_000, 2_500_000],
    ];

    public function test_it_seeds_every_category_and_product_of_the_source_list(): void
    {
        $catalog = require database_path('seeders/Catalog/catalog.php');

        $this->seed(CatalogSeeder::class);

        $this->assertSame(count($catalog['categories']), Category::query()->count());
        $this->assertSame(count($catalog['products']), Product::query()->count());

        $this->assertDatabaseHas('categories', ['slug' => 'peptidy', 'name' => 'Пептиды']);
        $this->assertDatabaseHas('products', ['slug' => 'epitalon', 'name' => 'Epitalon']);
        $this->assertDatabaseHas('products', ['slug' => 'lmax-climatic', 'name' => 'LMAX CLIMATIC']);
    }

    public function test_a_second_run_never_undoes_an_editors_work(): void
    {
        $this->seed(CatalogSeeder::class);

        // Всё, что редактируют руками на живом магазине: тексты, цена, валюта,
        // остаток, статус, название категории.
        Category::query()->where('slug', 'ingalyatory')->update(['name' => 'Ингаляторы / очищающие вейпы']);

        Product::query()->where('slug', 'epitalon')->update([
            'name' => 'Эпиталон 10 мг',
            'summary' => 'Наше описание, не из исходного списка.',
            'supplier' => 'Новый поставщик',
            'price_minor' => 777_00,
            'currency' => Currency::Usd,
            'stock' => 3,
            'status' => ProductStatus::Draft,
        ]);

        $countBefore = Product::query()->count();

        // Деплой перезапускает контейнер, а с ним и сидер.
        $this->seed(CatalogSeeder::class);

        $this->assertDatabaseHas('categories', ['slug' => 'ingalyatory', 'name' => 'Ингаляторы / очищающие вейпы']);
        $this->assertDatabaseHas('products', [
            'slug' => 'epitalon',
            'name' => 'Эпиталон 10 мг',
            'summary' => 'Наше описание, не из исходного списка.',
            'supplier' => 'Новый поставщик',
            'price_minor' => 777_00,
            'currency' => 'USD',
            'stock' => 3,
            'status' => ProductStatus::Draft->value,
        ]);
        $this->assertSame($countBefore, Product::query()->count());
    }

    public function test_a_deleted_product_does_not_come_back_with_the_next_deploy(): void
    {
        $this->seed(CatalogSeeder::class);

        Product::query()->where('slug', 'epitalon')->delete();
        $countAfterDelete = Product::query()->count();

        $this->seed(CatalogSeeder::class);

        // Товар убрали из продажи намеренно; перезапуск контейнера — не повод
        // возвращать его в каталог.
        $this->assertSame($countAfterDelete, Product::query()->count());
        $this->assertDatabaseMissing('products', ['slug' => 'epitalon']);
    }

    public function test_an_empty_catalog_is_filled_again(): void
    {
        $this->seed(CatalogSeeder::class);
        $seeded = Product::query()->count();

        Product::query()->delete();
        Category::query()->delete();

        // Осознанная перезаливка: таблицу очистили — список наливается заново.
        $this->seed(CatalogSeeder::class);

        $this->assertSame($seeded, Product::query()->count());
    }

    public function test_every_seeded_product_is_purchasable(): void
    {
        $this->seed(CatalogSeeder::class);

        $currency = Currency::from(config()->string('shop.currency'));

        Product::query()->with('category')->each(function (Product $product) use ($currency): void {
            $this->assertSame(ProductStatus::Published, $product->status);
            $this->assertSame($currency, $product->currency);
            $this->assertTrue($product->isAvailable());

            $this->assertGreaterThanOrEqual(5, $product->stock);
            $this->assertLessThanOrEqual(60, $product->stock);

            [$min, $max] = self::PRICE_RANGES_MINOR[$product->category->slug];
            $this->assertGreaterThanOrEqual($min, $product->price_minor);
            $this->assertLessThanOrEqual($max, $product->price_minor);
            $this->assertSame(0, $product->price_minor % 100_00, 'prices are whole units of currency');
        });
    }

    public function test_cyrillic_names_produce_transliterated_unique_slugs(): void
    {
        $this->seed(CatalogSeeder::class);

        $slugs = Product::query()->pluck('slug');

        $this->assertSame($slugs->count(), $slugs->unique()->count());
        $slugs->each(fn (string $slug) => $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug));

        $this->assertDatabaseHas('products', ['slug' => 'koltsa-datchiki']);
    }

    /**
     * Price and stock are derived from the slug, so the same source list always
     * seeds the same figures -- these are the values that derivation must produce.
     */
    public function test_price_and_stock_are_derived_deterministically_from_the_slug(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertDatabaseHas('products', ['slug' => 'koltsa-datchiki', 'price_minor' => 13_270_000, 'stock' => 14]);
        $this->assertDatabaseHas('products', ['slug' => 'epitalon', 'price_minor' => 4_200_000, 'stock' => 57]);
        $this->assertDatabaseHas('products', ['slug' => 'lmax-climatic', 'price_minor' => 1_610_000, 'stock' => 33]);
    }

    public function test_products_sharing_a_name_get_distinct_slugs_instead_of_overwriting_each_other(): void
    {
        $seeder = new class(config()) extends CatalogSeeder
        {
            protected function catalog(): array
            {
                return [
                    'categories' => [
                        ['slug' => 'peptidy', 'name' => 'Пептиды', 'description' => null, 'position' => 10],
                    ],
                    'products' => array_fill(0, 3, [
                        'category' => 'peptidy',
                        'name' => 'NAD+',
                        'summary' => null,
                        'maturity' => null,
                        'supplier' => null,
                        'source_url' => null,
                    ]),
                ];
            }
        };

        $seeder->run();

        $this->assertSame(['nad', 'nad-2', 'nad-3'], Product::query()->orderBy('slug')->pluck('slug')->all());
    }

    public function test_running_the_seeder_twice_does_not_duplicate_the_catalog(): void
    {
        $this->seed(CatalogSeeder::class);

        $categories = Category::query()->count();
        $products = Product::query()->count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame($categories, Category::query()->count());
        $this->assertSame($products, Product::query()->count());
    }
}
