<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->resource->number,
            'status' => $this->resource->status->value,
            'payment_status' => $this->resource->payment_status->value,
            'payment_method' => $this->resource->payment_method->value,
            'delivery_method' => $this->resource->delivery_method->value,
            'delivery_cost_minor' => $this->resource->delivery_cost_minor,
            'delivery_days_min' => $this->resource->delivery_days_min,
            'delivery_days_max' => $this->resource->delivery_days_max,
            'cdek_city_name' => $this->resource->cdek_city_name,
            'cdek_tariff_name' => $this->resource->cdek_tariff_name,
            'cdek_point_code' => $this->resource->cdek_point_code,
            'cdek_point_address' => $this->resource->cdek_point_address,
            'currency' => $this->resource->currency,
            'total_minor' => $this->resource->total_minor,
            'contact_name' => $this->resource->contact_name,
            'contact_email' => $this->resource->contact_email,
            'contact_phone' => $this->resource->contact_phone,
            'shipping_address' => $this->resource->shipping_address,
            'comment' => $this->resource->comment,
            'paid_at' => $this->resource->paid_at,
            'created_at' => $this->resource->created_at,
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
