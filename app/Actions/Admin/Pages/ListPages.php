<?php

namespace App\Actions\Admin\Pages;

use App\Models\Page;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListPages
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return LengthAwarePaginator<int, Page>
     */
    public function __invoke(?string $search, ?int $perPage): LengthAwarePaginator
    {
        return Page::query()
            ->when($search, static fn (Builder $query, string $term) => $query->where(
                static fn (Builder $nested) => $nested
                    ->where('title', 'like', '%'.$term.'%')
                    ->orWhere('slug', 'like', '%'.$term.'%'),
            ))
            ->orderBy('position')
            ->orderBy('title')
            ->paginate($perPage ?? $this->config->integer('shop.admin_per_page'))
            ->withQueryString();
    }
}
