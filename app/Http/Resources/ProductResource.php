<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $stockLevel = $this->resource->stockLevel();

        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'summary' => $this->resource->summary,
            'maturity' => $this->resource->maturity,
            'supplier' => $this->resource->supplier,
            'source_url' => $this->resource->source_url,
            'status' => $this->resource->status->value,
            'price_minor' => $this->resource->price_minor,
            'currency' => $this->resource->currency,
            'stock_level' => $stockLevel->value,
            'stock_label' => $stockLevel->label(),
            // Carried by the product itself: the catalog grid shows the terms
            // on every card and has no use for the whole category.
            'wholesale_only' => $this->resource->isWholesaleOnly(),
            'min_order_quantity' => $this->resource->minOrderQuantity(),
            'weight_grams' => $this->resource->weight_grams,
            'is_available' => $this->resource->isAvailable(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'cover' => $this->whenLoaded(
                'primaryImage',
                fn () => $this->resource->primaryImage === null
                    ? null
                    : new ProductImageResource($this->resource->primaryImage),
            ),
            'images' => $this->whenLoaded(
                'images',
                fn () => ProductImageResource::collection($this->resource->images),
            ),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
