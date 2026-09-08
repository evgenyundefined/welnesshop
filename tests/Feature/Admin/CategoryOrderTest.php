<?php

namespace Tests\Feature\Admin;

use App\Enums\CategoryMove;
use App\Models\Category;
use App\Models\Customer;
use Tests\TestCase;

class CategoryOrderTest extends TestCase
{
    public function test_an_admin_reorders_the_catalog_and_the_storefront_follows(): void
    {
        $this->signInAdmin();
        [$first, $second, $third] = $this->catalog();

        $this->assertSame([$first->id, $second->id, $third->id], $this->storefrontOrder());

        $this->putJson(route('admin.api.categories.move', $third), ['direction' => CategoryMove::Up->value])
            ->assertNoContent();

        $this->assertSame([$first->id, $third->id, $second->id], $this->storefrontOrder());

        $this->putJson(route('admin.api.categories.move', $first), ['direction' => CategoryMove::Down->value])
            ->assertNoContent();

        $this->assertSame([$third->id, $first->id, $second->id], $this->storefrontOrder());
    }

    public function test_the_admin_listing_reads_in_the_same_order_as_the_catalog(): void
    {
        $this->signInAdmin();
        [$first, $second, $third] = $this->catalog();

        $this->putJson(route('admin.api.categories.move', $third), ['direction' => CategoryMove::Up->value])
            ->assertNoContent();

        $listed = collect($this->getJson(route('admin.api.categories.index'))->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$first->id, $third->id, $second->id], $listed);
        $this->assertSame($listed, $this->storefrontOrder());
    }

    public function test_a_position_typed_into_the_form_moves_the_category_too(): void
    {
        $this->signInAdmin();
        [$first, $second, $third] = $this->catalog();

        $this->putJson(route('admin.api.categories.update', $third), [
            'name' => $third->name,
            'slug' => $third->slug,
            'position' => 0,
        ])->assertOk();

        $this->assertSame([$third->id, $first->id, $second->id], $this->storefrontOrder());
    }

    public function test_an_edge_move_is_accepted_and_changes_nothing(): void
    {
        $this->signInAdmin();
        [$first, $second, $third] = $this->catalog();

        $this->putJson(route('admin.api.categories.move', $first), ['direction' => CategoryMove::Up->value])
            ->assertNoContent();

        $this->assertSame([$first->id, $second->id, $third->id], $this->storefrontOrder());
    }

    public function test_the_direction_is_validated(): void
    {
        $this->signInAdmin();
        [$first, $second] = $this->catalog();

        $this->putJson(route('admin.api.categories.move', $second), ['direction' => 'sideways'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('direction');

        $this->putJson(route('admin.api.categories.move', $second), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('direction');

        $this->assertSame([$first->id, $second->id], array_slice($this->storefrontOrder(), 0, 2));
    }

    public function test_an_unknown_category_is_a_not_found(): void
    {
        $this->signInAdmin();

        $this->putJson(route('admin.api.categories.move', ['category' => 999]), ['direction' => 'up'])
            ->assertNotFound();
    }

    public function test_only_an_admin_can_reorder_the_catalog(): void
    {
        [$first, $second, $third] = $this->catalog();

        $this->putJson(route('admin.api.categories.move', $third), ['direction' => 'up'])->assertUnauthorized();

        $this->actingAs(Customer::factory()->create(), 'web');

        $this->putJson(route('admin.api.categories.move', $third), ['direction' => 'up'])->assertUnauthorized();

        $this->assertSame([$first->id, $second->id, $third->id], $this->storefrontOrder());
    }

    /** @return list<Category> */
    private function catalog(): array
    {
        return [
            Category::factory()->create(['name' => 'Первая', 'position' => 10]),
            Category::factory()->create(['name' => 'Вторая', 'position' => 20]),
            Category::factory()->create(['name' => 'Третья', 'position' => 30]),
        ];
    }

    /** @return list<int> */
    private function storefrontOrder(): array
    {
        return collect($this->getJson(route('api.categories'))->assertOk()->json('data'))->pluck('id')->all();
    }
}
