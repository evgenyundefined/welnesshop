<?php

namespace App\Http\Requests;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;

class ListOrdersRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(Config $config): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$config->integer('shop.max_orders_per_page')],
        ];
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
