<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'customer_id',
    'status',
    'payment_status',
    'payment_method',
    'delivery_method',
    'currency',
    'total_minor',
    'contact_name',
    'contact_email',
    'contact_phone',
    'shipping_address',
    'comment',
    'paid_at',
])]
#[RouteKey('number')]
class Order extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'delivery_method' => DeliveryMethod::class,
            'total_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
