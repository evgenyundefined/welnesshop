<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

/**
 * Раздел «в разработке» не просто показывает сообщение: его товары нигде не
 * выставляются. Иначе витрина предлагала бы купить то, о чём сама пишет, что
 * раздела ещё нет.
 */
class UnderDevelopmentCategoryTest extends TestCase
{
    private function developing(): Category
    {
        return Category::factory()->create(['under_development' => true, 'name' => 'Витамины и добавки']);
    }

    public function test_the_storefront_is_told_the_section_is_not_ready(): void
    {
        $category = $this->developing();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $category->slug)
            ->assertJsonPath('data.0.under_development', true);
    }

    public function test_its_products_are_not_listed_under_it(): void
    {
        $category = $this->developing();
        Product::factory()->count(3)->for($category)->create();

        $this->getJson("/api/products?category={$category->slug}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_its_products_do_not_leak_into_the_whole_catalog(): void
    {
        $open = Product::factory()->for(Category::factory())->create(['name' => 'Эпиталон']);
        Product::factory()->for($this->developing())->create(['name' => 'Витамин D']);

        $names = collect($this->getJson('/api/products')->assertOk()->json('data'))->pluck('name');

        $this->assertSame(['Эпиталон'], $names->all());
        $this->assertSame($open->id, $this->getJson('/api/products')->json('data.0.id'));
    }

    public function test_its_products_do_not_leak_into_the_footer(): void
    {
        Product::factory()->for($this->developing())->create(['name' => 'Витамин D']);
        Product::factory()->for(Category::factory())->create(['name' => 'Эпиталон']);

        $footer = collect($this->getJson('/api/site')->assertOk()->json('data.products'))->pluck('name');

        $this->assertSame(['Эпиталон'], $footer->all());
    }

    public function test_its_products_are_not_offered_to_a_search_engine(): void
    {
        $hidden = Product::factory()->for($this->developing())->create();
        $open = Product::factory()->for(Category::factory())->create();

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString("/products/{$open->slug}", $sitemap);
        $this->assertStringNotContainsString("/products/{$hidden->slug}", $sitemap);
    }

    public function test_opening_the_section_again_brings_its_products_back(): void
    {
        $category = $this->developing();
        Product::factory()->count(2)->for($category)->create();

        $category->update(['under_development' => false]);

        // Галочку снимают, когда раздел наполнили: ничего восстанавливать
        // руками не нужно.
        $this->getJson("/api/products?category={$category->slug}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_administrator_ticks_the_box(): void
    {
        $this->signInAdmin();
        $category = Category::factory()->create();

        $this->putJson(route('admin.api.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'under_development' => true,
        ])->assertOk()->assertJsonPath('data.under_development', true);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'under_development' => true]);
    }

    public function test_the_admin_still_sees_the_products_of_the_section(): void
    {
        $this->signInAdmin();
        $category = $this->developing();
        Product::factory()->count(3)->for($category)->create();

        // Раздел наполняют именно через админку — прятать товары от неё
        // означало бы, что доделать его нечем.
        $this->getJson(route('admin.api.products.index', ['category_id' => $category->id]))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
