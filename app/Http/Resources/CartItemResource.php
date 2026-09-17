<?php

namespace App\Http\Resources;

use App\Enums\Currency;
use App\Exchange\ConvertMoney;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    public function __construct(CartItem $item, private readonly ConvertMoney $convert)
    {
        parent::__construct($item);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Currency $currency */
        $currency = $this->resource->product->currency;
        $unitPrice = ($this->convert)($this->resource->product->price_minor, $currency);

        return [
            'id' => $this->resource->id,
            'quantity' => $this->resource->quantity,
            // Цена приведена к валюте расчётов — именно эту сумму спишут.
            // Исходная остаётся рядом, чтобы покупатель узнал ценник товара.
            'unit_price_minor' => $unitPrice,
            'total_minor' => $unitPrice * $this->resource->quantity,
            'original_currency' => $currency->value,
            'original_unit_price_minor' => $this->resource->product->price_minor,
            'product' => new ProductResource($this->resource->product),
        ];
    }
}
