<?php

namespace App\Http\Requests\Admin;

use Illuminate\Config\Repository as Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadContentImageRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(Config $config): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,avif,gif',
                'max:'.$config->integer('shop.images.max_kilobytes'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.image' => 'Загружать можно только изображения.',
            'image.mimes' => 'Поддерживаются форматы JPG, PNG, WEBP, AVIF и GIF.',
        ];
    }

    /**
     * Not named image(): an accessor called after the field it validates
     * recurses until the process dies.
     */
    public function uploadedImage(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('image');

        return $file;
    }
}
