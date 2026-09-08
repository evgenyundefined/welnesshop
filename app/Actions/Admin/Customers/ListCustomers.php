<?php

namespace App\Actions\Admin\Customers;

use App\Models\Customer;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function __invoke(?string $search, ?bool $blocked, ?int $perPage): LengthAwarePaginator
    {
        return Customer::query()
            ->withCount('orders')
            ->when($search, static fn (Builder $query, string $term) => $query->where(
                static fn (Builder $nested) => $nested
                    ->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%'),
            ))
            ->when($blocked !== null, fn (Builder $query) => $blocked
                ? $query->whereNotNull('blocked_at')
                : $query->whereNull('blocked_at'))
            ->latest('created_at')
            ->paginate($perPage ?? $this->config->integer('shop.admin_per_page'))
            ->withQueryString();
    }
}
