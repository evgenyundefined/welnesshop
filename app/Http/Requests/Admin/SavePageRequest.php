<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SavePageRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'between:2,255'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('pages', 'slug')->ignore($this->route('page')),
            ],
            'body' => ['required', 'string', 'max:50000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и дефис.',
            'slug.required' => 'Не удалось собрать slug из заголовка — укажите его вручную.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug($this->string('title')->toString(), '-', 'ru') ?: null]);
        }
    }

    /** @return array<string, mixed> */
    public function page(): array
    {
        return [
            'title' => trim($this->string('title')->toString()),
            'slug' => $this->string('slug')->toString(),
            'body' => trim($this->string('body')->toString()),
            'position' => $this->filled('position') ? $this->integer('position') : 0,
            'is_published' => $this->boolean('is_published'),
        ];
    }
}
