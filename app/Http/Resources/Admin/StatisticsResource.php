<?php

namespace App\Http\Resources\Admin;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class StatisticsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'period' => $this->resource['period']->value,
            'since' => $this->resource['since']?->toDateString(),
            'orders' => $this->resource['orders'],
            'revenue' => $this->resource['revenue'],
            'customers' => $this->resource['customers'],
            'daily' => $this->resource['daily'],
            'top_products' => $this->resource['top_products']
                ->map(static fn (OrderItem $item): array => [
                    'name' => $item->product_name,
                    'slug' => $item->product_slug,
                    'quantity' => (int) $item->quantity,
                    'revenue_minor' => (int) $item->revenue_minor,
                ])
                ->all(),
            'most_viewed' => $this->resource['most_viewed']
                ->map(static fn (Product $product): array => [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'views' => $product->views,
                ])
                ->all(),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
