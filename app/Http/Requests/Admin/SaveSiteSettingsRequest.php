<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SaveSiteSettingsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'banner_enabled' => ['nullable', 'boolean'],
            'banner_title' => ['nullable', 'string', 'max:255'],
            'banner_subtitle' => ['nullable', 'string', 'max:500'],
            'banner_button_label' => ['nullable', 'string', 'max:80'],
            'banner_button_url' => ['nullable', 'string', 'max:500'],
            'promo_heading' => ['nullable', 'string', 'max:255'],
            'promo_body' => ['nullable', 'string', 'max:100000'],
            'disclaimer' => ['nullable', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            'banner_enabled' => $this->boolean('banner_enabled'),
            ...collect([
                'banner_title',
                'banner_subtitle',
                'banner_button_label',
                'banner_button_url',
                'promo_heading',
                'promo_body',
                'disclaimer',
                'contact_phone',
            ])->mapWithKeys(fn (string $field): array => [
                $field => $this->filled($field) ? trim($this->string($field)->toString()) : null,
            ])->all(),
            'contact_email' => $this->filled('contact_email')
                ? Str::lower(trim($this->string('contact_email')->toString()))
                : null,
        ];
    }
}
