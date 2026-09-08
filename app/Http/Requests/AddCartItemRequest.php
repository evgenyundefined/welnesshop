<?php

namespace App\Http\Requests;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(Config $config): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$config->integer('shop.max_item_quantity')],
        ];
    }

    public function productId(): int
    {
        return $this->integer('product_id');
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
