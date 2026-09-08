<?php

namespace App\Actions\Admin\Categories;

use App\Enums\CategoryMove;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class MoveCategory
{
    /**
     * Swaps a category with its neighbour in the order the catalog reads, and
     * renumbers the whole list to a clean 1..N sequence, so hand-typed
     * positions with gaps or ties can never leave two categories fighting over
     * the same slot.
     *
     * @throws \Throwable
     */
    public function __invoke(Category $category, CategoryMove $direction): void
    {
        DB::transaction(function () use ($category, $direction): void {
            $ordered = Category::query()
                ->orderBy('position')
                ->orderBy('name')
                ->lockForUpdate()
                ->get();

            $index = $ordered->search(static fn (Category $item): bool => $item->is($category));
            $target = $index + $direction->offset();

            if ($target < 0 || $target >= $ordered->count()) {
                return;
            }

            $slots = $ordered->all();
            [$slots[$index], $slots[$target]] = [$slots[$target], $slots[$index]];

            foreach ($slots as $slot => $item) {
                if ($item->position !== $slot + 1) {
                    $item->update(['position' => $slot + 1]);
                }
            }
        });
    }
}
