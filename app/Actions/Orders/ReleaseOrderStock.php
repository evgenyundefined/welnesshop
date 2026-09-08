<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Returns the units an order was holding back to the catalog. Used when an
 * order is cancelled and when one is deleted outright.
 */
class ReleaseOrderStock
{
    /**
     * @throws \Throwable
     */
    public function __invoke(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->loadMissing('items');

            $products = Product::query()
                ->whereIn('id', $order->items->pluck('product_id')->filter())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $order->items->each(function (OrderItem $item) use ($products): void {
                $products->get($item->product_id)?->increment('stock', $item->quantity);
            });
        });
    }
}
