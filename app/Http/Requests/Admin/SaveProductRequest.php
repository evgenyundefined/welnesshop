<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'between:2,255'],
            'slug' => [
                'required',
                'string',
                'max:190',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
            'summary' => ['nullable', 'string', 'max:2000'],
            'maturity' => ['nullable', 'string', 'max:2000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price_minor' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'currency' => ['nullable', 'string', 'size:3'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'weight_grams' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и дефис.',
            'slug.required' => 'Не удалось собрать slug из названия — укажите его вручную.',
        ];
    }

    /**
     * Deriving the slug before validation is what makes the unique rule cover
     * the generated value too, instead of letting it hit the database.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString(), '-', 'ru') ?: null]);
        }
    }

    /** @return array<string, mixed> */
    public function product(Config $config): array
    {
        $name = trim($this->string('name')->toString());

        return [
            'category_id' => $this->integer('category_id'),
            'name' => $name,
            'slug' => $this->string('slug')->toString(),
            'summary' => $this->filled('summary') ? trim($this->string('summary')->toString()) : null,
            'maturity' => $this->filled('maturity') ? trim($this->string('maturity')->toString()) : null,
            'supplier' => $this->filled('supplier') ? trim($this->string('supplier')->toString()) : null,
            'source_url' => $this->filled('source_url') ? $this->string('source_url')->toString() : null,
            'status' => $this->enum('status', ProductStatus::class),
            'price_minor' => $this->integer('price_minor'),
            'currency' => $this->filled('currency')
                ? Str::upper($this->string('currency')->toString())
                : $config->string('shop.currency'),
            'stock' => $this->integer('stock'),
            'weight_grams' => $this->filled('weight_grams') ? $this->integer('weight_grams') : null,
        ];
    }
}
