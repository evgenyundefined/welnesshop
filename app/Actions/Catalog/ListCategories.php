<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListCategories
{
    /**
     * @return Collection<int, Category>
     */
    public function __invoke(): Collection
    {
        return Category::query()
            ->withCount(['products' => static fn (Builder $query) => $query->published()])
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }
}
