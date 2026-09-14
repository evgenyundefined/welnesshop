<?php

namespace App\Http\Requests\Admin;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadLogoImageRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            'image' => [
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
            'image.image' => 'Логотип должен быть изображением.',
            'image.mimes' => 'Поддерживаются форматы JPG, PNG, WEBP и AVIF.',
        ];
    }

    /**
     * Named for the logo rather than for the field: an accessor called image()
     * on a request validating an "image" field recurses until the process dies.
     */
    public function logoImage(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('image');

        return $file;
    }
}
