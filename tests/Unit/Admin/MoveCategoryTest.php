<?php

namespace Tests\Unit\Admin;

use App\Actions\Admin\Categories\MoveCategory;
use App\Enums\CategoryMove;
use App\Models\Category;
use Tests\TestCase;

class MoveCategoryTest extends TestCase
{
    public function test_moving_up_swaps_a_category_with_the_one_above_it(): void
    {
        [$first, $second, $third] = $this->catalog();

        ($this->app->make(MoveCategory::class))($second, CategoryMove::Up);

        $this->assertSame([$second->id, $first->id, $third->id], $this->order());
    }

    public function test_moving_down_swaps_a_category_with_the_one_below_it(): void
    {
        [$first, $second, $third] = $this->catalog();

        ($this->app->make(MoveCategory::class))($second, CategoryMove::Down);

        $this->assertSame([$first->id, $third->id, $second->id], $this->order());
    }

    public function test_the_first_category_cannot_go_any_higher(): void
    {
        [$first, $second, $third] = $this->catalog();

        ($this->app->make(MoveCategory::class))($first, CategoryMove::Up);

        $this->assertSame([$first->id, $second->id, $third->id], $this->order());
    }

    public function test_the_last_category_cannot_go_any_lower(): void
    {
        [$first, $second, $third] = $this->catalog();

        ($this->app->make(MoveCategory::class))($third, CategoryMove::Down);

        $this->assertSame([$first->id, $second->id, $third->id], $this->order());
    }

    public function test_a_move_renumbers_the_list_into_a_clean_sequence(): void
    {
        $this->catalog();

        $moved = Category::query()->orderBy('position')->orderBy('name')->get()->last();

        ($this->app->make(MoveCategory::class))($moved, CategoryMove::Up);

        $this->assertSame([1, 2, 3], Category::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_categories_sharing_a_position_are_separated_by_the_move(): void
    {
        $b = Category::factory()->create(['name' => 'Bbb', 'position' => 5]);
        $a = Category::factory()->create(['name' => 'Aaa', 'position' => 5]);
        $c = Category::factory()->create(['name' => 'Ccc', 'position' => 5]);

        ($this->app->make(MoveCategory::class))($c, CategoryMove::Up);

        $this->assertSame([$a->id, $c->id, $b->id], $this->order());
        $this->assertSame([1, 2, 3], Category::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_a_move_leaves_everything_but_the_position_alone(): void
    {
        [$first, $second] = $this->catalog();

        ($this->app->make(MoveCategory::class))($second, CategoryMove::Up);

        $fresh = Category::query()->find($second->id);

        $this->assertSame($second->name, $fresh->name);
        $this->assertSame($second->slug, $fresh->slug);
        $this->assertSame($second->description, $fresh->description);
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
    private function order(): array
    {
        return Category::query()->orderBy('position')->orderBy('name')->pluck('id')->all();
    }
}
