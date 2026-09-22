<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'default_category_id',
    'seo_title',
    'seo_description',
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
    /** @return BelongsTo<Category, $this> */
    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    /** @return Attribute<?string, never> */
    protected function logoImageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_image_path === null
            ? null
            : Storage::url($this->logo_image_path));
    }

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
