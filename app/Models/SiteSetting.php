<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'banner_enabled',
    'banner_title',
    'banner_subtitle',
    'banner_button_label',
    'banner_button_url',
    'promo_heading',
    'promo_body',
    'disclaimer',
    'contacts_body',
    'info_body',
])]
class SiteSetting extends Model
{
    /** @return Attribute<?string, never> */
    protected function bannerImageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->banner_image_path === null
            ? null
            : Storage::url($this->banner_image_path));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'banner_enabled' => 'boolean',
        ];
    }
}
