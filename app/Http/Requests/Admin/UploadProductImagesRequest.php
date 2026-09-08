<?php

namespace App\Http\Requests\Admin;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadProductImagesRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.$config->integer('shop.images.max_per_product')],
            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,avif',
                'max:'.$config->integer('shop.images.max_kilobytes'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'images.*.image' => 'Загружать можно только изображения.',
            'images.*.mimes' => 'Поддерживаются форматы JPG, PNG, WEBP и AVIF.',
        ];
    }

    /** @return list<UploadedFile> */
    public function images(): array
    {
        return array_values(array_filter(
            (array) $this->file('images'),
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}
