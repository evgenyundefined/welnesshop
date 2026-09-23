<?php

namespace App\Http\Requests\Admin;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            'name' => ['required', 'string', 'between:2,255'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'wholesale_only' => ['nullable', 'boolean'],
            'under_development' => ['nullable', 'boolean'],
            'min_order_quantity' => ['nullable', 'integer', 'min:1', 'max:'.$config->integer('shop.max_item_quantity')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и дефис.',
            'slug.required' => 'Не удалось собрать slug из названия — укажите его вручную.',
            'min_order_quantity.max' => 'Минимум не может быть больше предела на один товар в корзине.',
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

    /**
     * @return array{
     *     slug: string,
     *     name: string,
     *     description: ?string,
     *     position: int,
     *     wholesale_only: bool,
     *     under_development: bool,
     *     min_order_quantity: int
     * }
     */
    public function category(): array
    {
        $name = trim($this->string('name')->toString());

        return [
            'name' => $name,
            'slug' => $this->string('slug')->toString(),
            'description' => $this->filled('description') ? trim($this->string('description')->toString()) : null,
            'position' => $this->filled('position') ? $this->integer('position') : 0,
            'wholesale_only' => $this->boolean('wholesale_only'),
            'under_development' => $this->boolean('under_development'),
            'min_order_quantity' => $this->filled('min_order_quantity') ? $this->integer('min_order_quantity') : 1,
        ];
    }
}
