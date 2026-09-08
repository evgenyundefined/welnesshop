<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    public function test_categories_endpoint_counts_only_published_products(): void
    {
        $category = Category::factory()->create(['name' => 'Пептиды', 'position' => 1]);
        Product::factory()->count(2)->for($category)->create();
        Product::factory()->for($category)->draft()->create();

        $payload = collect($this->getJson(route('api.categories'))->assertOk()->json('data'))
            ->firstWhere('slug', $category->slug);

        $this->assertSame('Пептиды', $payload['name']);
        $this->assertSame(2, $payload['products_count']);
    }

    public function test_products_endpoint_hides_unpublished_products(): void
    {
        $published = $this->makeProduct();
        $draft = $this->makeProduct(['status' => ProductStatus::Draft]);

        $slugs = collect($this->getJson(route('api.products', ['per_page' => 50]))->assertOk()->json('data'))
            ->pluck('slug');

        $this->assertTrue($slugs->contains($published->slug));
        $this->assertFalse($slugs->contains($draft->slug));
    }

    public function test_products_endpoint_filters_by_category(): void
    {
        $peptides = Category::factory()->create();
        $devices = Category::factory()->create();

        $target = Product::factory()->for($peptides)->create();
        Product::factory()->for($devices)->create();

        $response = $this->getJson(route('api.products', ['category' => $peptides->slug]))->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertSame($target->slug, $response->json('data.0.slug'));
    }

    public function test_search_matches_a_term_anywhere_in_the_name_or_the_summary(): void
    {
        $category = Category::factory()->create();

        $inName = Product::factory()->for($category)->create([
            'name' => 'Комплекс Epitalon форте',
            'summary' => 'без совпадения',
        ]);
        $inSummary = Product::factory()->for($category)->create([
            'name' => 'без совпадения',
            'summary' => 'Пептид Epitalon для longevity-программ',
        ]);
        Product::factory()->for($category)->create(['name' => 'Selank', 'summary' => 'анксиолитик']);

        $response = $this->getJson(route('api.products', [
            'category' => $category->slug,
            'search' => 'Epitalon',
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$inName->slug, $inSummary->slug],
            collect($response->json('data'))->pluck('slug')->all(),
        );
    }

    public function test_products_endpoint_supports_every_sort_order(): void
    {
        $category = Category::factory()->create();

        $cheapAndOld = Product::factory()->for($category)->create([
            'name' => 'Alpha',
            'price_minor' => 100_00,
            'created_at' => now()->subDay(),
        ]);
        $expensiveAndNew = Product::factory()->for($category)->create([
            'name' => 'Beta',
            'price_minor' => 900_00,
            'created_at' => now(),
        ]);

        $expectations = [
            'name' => [$cheapAndOld->slug, $expensiveAndNew->slug],
            'price_asc' => [$cheapAndOld->slug, $expensiveAndNew->slug],
            'price_desc' => [$expensiveAndNew->slug, $cheapAndOld->slug],
            'newest' => [$expensiveAndNew->slug, $cheapAndOld->slug],
        ];

        foreach ($expectations as $sort => $expected) {
            $response = $this->getJson(route('api.products', [
                'category' => $category->slug,
                'sort' => $sort,
            ]))->assertOk();

            $this->assertSame($expected, collect($response->json('data'))->pluck('slug')->all(), $sort);
        }
    }

    public function test_products_endpoint_sorts_by_name_when_no_sort_is_given(): void
    {
        $category = Category::factory()->create();

        $beta = Product::factory()->for($category)->create(['name' => 'Beta']);
        $alpha = Product::factory()->for($category)->create(['name' => 'Alpha']);

        $response = $this->getJson(route('api.products', ['category' => $category->slug]))->assertOk();

        $this->assertSame([$alpha->slug, $beta->slug], collect($response->json('data'))->pluck('slug')->all());
    }

    public function test_products_endpoint_can_drop_everything_out_of_stock(): void
    {
        $category = Category::factory()->create();
        $inStock = Product::factory()->for($category)->create(['stock' => 1]);
        $outOfStock = Product::factory()->for($category)->outOfStock()->create();

        $slugs = collect(
            $this->getJson(route('api.products', ['category' => $category->slug, 'in_stock' => 1]))
                ->assertOk()
                ->json('data'),
        )->pluck('slug');

        $this->assertTrue($slugs->contains($inStock->slug));
        $this->assertFalse($slugs->contains($outOfStock->slug));
    }

    public function test_product_endpoint_serves_published_products_only(): void
    {
        $product = $this->makeProduct(['supplier' => 'Корея']);

        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonPath('data.supplier', 'Корея')
            ->assertJsonPath('data.category.id', $product->category_id);

        $product->update(['status' => ProductStatus::Draft]);

        $this->getJson(route('api.products.show', $product))->assertNotFound();
    }
}
