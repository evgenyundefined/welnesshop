<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'quantity' => $this->resource->quantity,
            'unit_price_minor' => $this->resource->product->price_minor,
            'total_minor' => $this->resource->totalMinor(),
            'product' => new ProductResource($this->resource->product),
        ];
    }
}
