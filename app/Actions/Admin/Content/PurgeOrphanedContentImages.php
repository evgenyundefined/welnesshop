<?php

namespace App\Actions\Admin\Content;

use App\Support\ContentImagePaths;
use Illuminate\Contracts\Filesystem\Filesystem;

class PurgeOrphanedContentImages
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly ContentImagePaths $paths,
    ) {}

    /**
     * Pictures uploaded from the editor belong to the text they were pasted
     * into, so they are dropped once no saved text mentions them any more.
     * Only the texts that just changed are examined: an upload that has not
     * been saved into a body yet is invisible here and survives.
     */
    public function __invoke(string ...$replacedHtml): void
    {
        foreach ($this->paths->in(...$replacedHtml)->diff($this->paths->referenced()) as $path) {
            $this->disk->delete($path);
        }
    }
}
