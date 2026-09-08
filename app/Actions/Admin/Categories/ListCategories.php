<?php

namespace App\Actions\Admin\Categories;

use App\Models\Category;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListCategories
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function __invoke(?string $search, ?int $perPage): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('products')
            ->when($search, static fn (Builder $query, string $term) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate($perPage ?? $this->config->integer('shop.admin_per_page'))
            ->withQueryString();
    }
}
