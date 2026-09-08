<?php

namespace App\Actions\Admin\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListOrders
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function __invoke(?string $search, ?OrderStatus $status, ?int $perPage): LengthAwarePaginator
    {
        return Order::query()
            ->with('customer')
            ->withCount('items')
            ->when($search, static fn (Builder $query, string $term) => $query->where(
                static fn (Builder $nested) => $nested
                    ->where('number', 'like', '%'.$term.'%')
                    ->orWhere('contact_name', 'like', '%'.$term.'%')
                    ->orWhere('contact_email', 'like', '%'.$term.'%'),
            ))
            ->when($status, static fn (Builder $query, OrderStatus $value) => $query->where('status', $value))
            ->latest('created_at')
            ->paginate($perPage ?? $this->config->integer('shop.admin_per_page'))
            ->withQueryString();
    }
}
