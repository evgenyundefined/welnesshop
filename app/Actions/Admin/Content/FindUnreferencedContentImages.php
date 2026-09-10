<?php

namespace App\Actions\Admin\Content;

use App\Support\ContentImagePaths;
use Illuminate\Support\Collection;

class FindUnreferencedContentImages
{
    public function __construct(private readonly ContentImagePaths $paths) {}

    /**
     * Sweeps the whole directory, unlike PurgeOrphanedContentImages, so it
     * also reports a picture that is still waiting to be saved into a page.
     * That is why the command built on it asks before deleting anything.
     *
     * @return Collection<int, string>
     */
    public function __invoke(): Collection
    {
        return $this->paths->stored()->diff($this->paths->referenced())->values();
    }
}
