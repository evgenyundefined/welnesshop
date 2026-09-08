<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'currency' => config('shop.currency'),
            'total_minor' => $this->resource->totalMinor(),
            'total_quantity' => $this->resource->totalQuantity(),
            'items' => CartItemResource::collection($this->resource->items),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
