<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveSiteSettingsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'default_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'banner_enabled' => ['nullable', 'boolean'],
            'banner_title' => ['nullable', 'string', 'max:255'],
            'banner_subtitle' => ['nullable', 'string', 'max:500'],
            'banner_button_label' => ['nullable', 'string', 'max:80'],
            'banner_button_url' => ['nullable', 'string', 'max:500'],
            'promo_heading' => ['nullable', 'string', 'max:255'],
            'promo_body' => ['nullable', 'string', 'max:100000'],
            'disclaimer' => ['nullable', 'string', 'max:5000'],
            'contacts_body' => ['nullable', 'string', 'max:20000'],
            'info_body' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            'banner_enabled' => $this->boolean('banner_enabled'),
            'default_category_id' => $this->filled('default_category_id') ? $this->integer('default_category_id') : null,
            ...collect([
                'seo_title',
                'seo_description',
                'banner_title',
                'banner_subtitle',
                'banner_button_label',
                'banner_button_url',
                'promo_heading',
                'promo_body',
                'disclaimer',
                'contacts_body',
                'info_body',
            ])->mapWithKeys(fn (string $field): array => [
                $field => $this->filled($field) ? trim($this->string($field)->toString()) : null,
            ])->all(),
        ];
    }
}
