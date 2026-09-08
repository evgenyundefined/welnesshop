<?php

namespace App\Actions\Admin\Products;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListProducts
{
    public function __construct(private readonly Config $config) {}

    /**
     * Unlike the storefront listing this one shows every status, since drafts
     * and archived products are exactly what an administrator needs to see.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function __invoke(
        ?string $search,
        ?int $categoryId,
        ?ProductStatus $status,
        ?int $perPage,
    ): LengthAwarePaginator {
        return Product::query()
            ->with(['category', 'primaryImage'])
            ->when($search, static fn (Builder $query, string $term) => $query->where(
                static fn (Builder $nested) => $nested
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('slug', 'like', '%'.$term.'%'),
            ))
            ->when($categoryId, static fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($status, static fn (Builder $query, ProductStatus $value) => $query->where('status', $value))
            ->orderBy('name')
            ->paginate($perPage ?? $this->config->integer('shop.admin_per_page'))
            ->withQueryString();
    }
}
