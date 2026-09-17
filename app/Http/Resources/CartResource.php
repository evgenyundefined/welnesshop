<?php

namespace App\Http\Resources;

use App\Exchange\ConvertMoney;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    public function __construct(Cart $cart, private readonly ConvertMoney $convert)
    {
        parent::__construct($cart);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Корзина считается в одной валюте: складывать доллары с рублями
        // нельзя, а платить всё равно придётся чем-то одним.
        $items = $this->resource->items
            ->map(fn (CartItem $item): array => (new CartItemResource($item, $this->convert))->toArray($request));

        return [
            'id' => $this->resource->id,
            'currency' => $this->convert->settlement()->value,
            'total_minor' => (int) $items->sum('total_minor'),
            'total_quantity' => $this->resource->totalQuantity(),
            'items' => $items->all(),
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(Response::HTTP_OK);
    }
}
