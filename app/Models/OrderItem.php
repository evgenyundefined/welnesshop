<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'product_id',
    'product_name',
    'product_slug',
    'unit_price_minor',
    'quantity',
    'total_minor',
    'original_currency',
    'original_unit_price_minor',
    'exchange_rate',
])]
class OrderItem extends Model
{
    /** Цена назначена в валюте товара, а списана в валюте расчётов. */
    public function wasConverted(): bool
    {
        return $this->original_currency !== $this->order->currency;
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'quantity' => 'integer',
            'total_minor' => 'integer',
            'original_currency' => Currency::class,
            'original_unit_price_minor' => 'integer',
            'exchange_rate' => 'float',
        ];
    }
}
