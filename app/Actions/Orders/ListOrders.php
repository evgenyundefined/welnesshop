<?php

namespace App\Actions\Orders;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListOrders
{
    public function __construct(private readonly Config $config) {}

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function __invoke(Customer $customer, ?int $perPage): LengthAwarePaginator
    {
        return $customer->orders()
            ->withCount('items')
            ->latest('created_at')
            ->paginate($perPage ?? $this->config->integer('shop.orders_per_page'));
    }
}
