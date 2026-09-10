<?php

namespace App\Http\Requests\Admin;

use App\Enums\PageVisibility;
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
            'body' => ['required', 'string', 'max:200000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'visibility' => ['required', Rule::enum(PageVisibility::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и дефис.',
            'slug.required' => 'Не удалось собрать slug из заголовка — укажите его вручную.',
            'visibility.required' => 'Выберите, где страница показывается.',
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
            'visibility' => $this->enum('visibility', PageVisibility::class),
        ];
    }
}
