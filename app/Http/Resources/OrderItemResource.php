<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_id' => $this->resource->product_id,
            'product_name' => $this->resource->product_name,
            'product_slug' => $this->resource->product_slug,
            'unit_price_minor' => $this->resource->unit_price_minor,
            'quantity' => $this->resource->quantity,
            'total_minor' => $this->resource->total_minor,
        ];
    }
}
