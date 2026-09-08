<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAdmin();
    }

    public function test_the_listing_is_ordered_paginated_and_counts_products(): void
    {
        $first = Category::factory()->create(['name' => 'Пептиды', 'position' => 1]);
        $second = Category::factory()->create(['name' => 'Витамины', 'position' => 2]);
        $this->makeProduct(['category_id' => $first->id]);
        $this->makeProduct(['category_id' => $first->id]);

        $this->getJson(route('admin.api.categories.index', ['per_page' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.products_count', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1);

        $this->getJson(route('admin.api.categories.index', ['per_page' => 1, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.products_count', 0);
    }

    public function test_the_listing_can_be_searched(): void
    {
        $peptides = Category::factory()->create(['name' => 'Пептиды']);
        Category::factory()->create(['name' => 'Витамины']);

        $this->getJson(route('admin.api.categories.index', ['search' => 'Пепт']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $peptides->id);
    }

    public function test_the_listing_does_not_query_per_row(): void
    {
        Category::factory()->count(5)->create();

        DB::enableQueryLog();

        $this->getJson(route('admin.api.categories.index'))->assertOk()->assertJsonCount(5, 'data');

        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));

        DB::disableQueryLog();
    }

    public function test_an_admin_can_create_a_category_with_a_derived_slug(): void
    {
        $this->postJson(route('admin.api.categories.store'), [
            'name' => '  Пептиды и биорегуляторы  ',
            'description' => 'Описание',
            'position' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Пептиды и биорегуляторы')
            ->assertJsonPath('data.slug', 'peptidy-i-bioregulyatory')
            ->assertJsonPath('data.position', 3)
            ->assertJsonPath('data.products_count', 0);

        $this->assertDatabaseHas('categories', [
            'slug' => 'peptidy-i-bioregulyatory',
            'name' => 'Пептиды и биорегуляторы',
            'position' => 3,
        ]);
    }

    public function test_a_derived_slug_that_collides_is_rejected_by_validation_not_by_the_database(): void
    {
        Category::factory()->create(['slug' => 'peptidy', 'name' => 'Пептиды']);

        $this->postJson(route('admin.api.categories.store'), ['name' => 'Пептиды'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame(1, Category::query()->count());
    }

    public function test_a_slug_that_is_not_url_safe_is_rejected(): void
    {
        $this->postJson(route('admin.api.categories.store'), ['name' => 'Пептиды', 'slug' => 'Не Slug!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_a_name_that_produces_no_slug_asks_for_one_explicitly(): void
    {
        $this->postJson(route('admin.api.categories.store'), ['name' => '!!!'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.slug.0', 'Не удалось собрать slug из названия — укажите его вручную.');
    }

    public function test_an_admin_can_read_a_single_category(): void
    {
        $category = Category::factory()->create();
        $this->makeProduct(['category_id' => $category->id]);

        $this->getJson(route('admin.api.categories.show', $category))
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.products_count', 1);
    }

    public function test_an_admin_can_update_a_category_and_keep_its_own_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'peptidy', 'name' => 'Пептиды', 'position' => 0]);

        $this->putJson(route('admin.api.categories.update', $category), [
            'name' => 'Пептиды и биорегуляторы',
            'slug' => 'peptidy',
            'description' => null,
            'position' => 7,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Пептиды и биорегуляторы')
            ->assertJsonPath('data.slug', 'peptidy')
            ->assertJsonPath('data.description', null);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Пептиды и биорегуляторы',
            'slug' => 'peptidy',
            'position' => 7,
        ]);
    }

    public function test_an_update_cannot_steal_another_categorys_slug(): void
    {
        Category::factory()->create(['slug' => 'vitaminy']);
        $category = Category::factory()->create(['slug' => 'peptidy']);

        $this->putJson(route('admin.api.categories.update', $category), [
            'name' => 'Пептиды',
            'slug' => 'vitaminy',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertSame('peptidy', $category->refresh()->slug);
    }

    public function test_an_admin_can_delete_an_empty_category(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson(route('admin.api.categories.destroy', $category))->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_a_category_holding_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        $this->makeProduct(['category_id' => $category->id]);

        $this->deleteJson(route('admin.api.categories.destroy', $category))
            ->assertConflict()
            ->assertJsonPath('message', 'Нельзя удалить категорию, в которой есть товары.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_an_unknown_category_is_a_not_found(): void
    {
        $this->getJson(route('admin.api.categories.show', ['category' => 999]))->assertNotFound();
    }

    public function test_a_customer_cannot_write_to_the_catalog(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs(Customer::factory()->create(), 'web');

        $category = Category::factory()->create();

        $this->postJson(route('admin.api.categories.store'), ['name' => 'Взлом'])->assertUnauthorized();
        $this->deleteJson(route('admin.api.categories.destroy', $category))->assertUnauthorized();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('categories', ['name' => 'Взлом']);
    }
}
