<?php

namespace App\Actions\Catalog;

use App\Enums\ProductSort;
use App\Models\Product;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListProducts
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function __invoke(
        ?string $categorySlug,
        ?string $search,
        ProductSort $sort,
        bool $inStockOnly,
        ?int $perPage,
    ): LengthAwarePaginator {
        [$column, $direction] = $sort->toOrderBy();

        return Product::query()
            ->published()
            ->with(['category', 'primaryImage'])
            ->when($categorySlug, static fn (Builder $query, string $slug) => $query->whereRelation('category', 'slug', $slug))
            ->when($search, static fn (Builder $query, string $term) => $query->where(
                static fn (Builder $nested) => $nested
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('summary', 'like', '%'.$term.'%'),
            ))
            ->when($inStockOnly, static fn (Builder $query) => $query->where('stock', '>', 0))
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate($perPage ?? $this->config->integer('shop.products_per_page'))
            ->withQueryString();
    }
}
