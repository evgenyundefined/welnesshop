<?php

namespace App\Http\Requests;

use App\Enums\ProductSort;
use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::enum(ProductSort::class)],
            'in_stock' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$config->integer('shop.max_products_per_page')],
        ];
    }

    public function categorySlug(): ?string
    {
        return $this->filled('category') ? $this->string('category')->toString() : null;
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->toString() : null;
    }

    public function sort(): ProductSort
    {
        return $this->enum('sort', ProductSort::class) ?? ProductSort::Name;
    }

    public function inStockOnly(): bool
    {
        return $this->boolean('in_stock');
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
