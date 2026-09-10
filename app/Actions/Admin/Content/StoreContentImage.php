<?php

namespace App\Actions\Admin\Content;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

class StoreContentImage
{
    public function __construct(private readonly Filesystem $disk) {}

    /**
     * Pictures pasted into a page are kept apart from product galleries: they
     * belong to the text, not to a product, and nothing cleans them up when a
     * product goes away.
     */
    public function __invoke(UploadedFile $file): string
    {
        return $this->disk->url($this->disk->putFile('content', $file));
    }
}
