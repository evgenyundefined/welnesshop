<?php

namespace App\Http\Requests;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(Config $config): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$config->integer('shop.max_item_quantity')],
        ];
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }
}
