<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SiteSetting;
use Tests\TestCase;

class DefaultCategoryTest extends TestCase
{
    public function test_the_storefront_is_told_which_category_to_open_on(): void
    {
        $peptides = Category::factory()->create(['slug' => 'peptidy', 'name' => 'Пептиды']);

        SiteSetting::query()->sole()->forceFill(['default_category_id' => $peptides->id])->save();

        // Витрина работает со slug'ами: идентификатор ей ни о чём не говорит.
        $this->getJson('/api/site')->assertOk()->assertJsonPath('data.default_category', 'peptidy');
    }

    public function test_without_a_choice_the_catalog_opens_on_everything(): void
    {
        SiteSetting::query()->sole()->forceFill(['default_category_id' => null])->save();

        $this->getJson('/api/site')->assertOk()->assertJsonPath('data.default_category', null);
    }

    public function test_an_administrator_picks_the_category(): void
    {
        $this->signInAdmin();
        $category = Category::factory()->create();

        $this->putJson(route('admin.api.site.update'), ['default_category_id' => $category->id])
            ->assertOk()
            ->assertJsonPath('data.default_category_id', $category->id);

        $this->assertDatabaseHas('site_settings', ['default_category_id' => $category->id]);
    }

    public function test_the_choice_can_be_cleared_back_to_everything(): void
    {
        $this->signInAdmin();
        $category = Category::factory()->create();
        SiteSetting::query()->sole()->forceFill(['default_category_id' => $category->id])->save();

        $this->putJson(route('admin.api.site.update'), ['default_category_id' => null])->assertOk();

        $this->assertDatabaseHas('site_settings', ['default_category_id' => null]);
    }

    public function test_a_deleted_category_does_not_leave_the_catalog_pointing_at_nothing(): void
    {
        $category = Category::factory()->create();
        SiteSetting::query()->sole()->forceFill(['default_category_id' => $category->id])->save();

        $category->delete();

        // Ссылка обнуляется базой, а не оставляет витрину с несуществующим
        // фильтром, по которому каталог был бы пуст.
        $this->assertDatabaseHas('site_settings', ['default_category_id' => null]);
        $this->getJson('/api/site')->assertOk()->assertJsonPath('data.default_category', null);
    }
}
