<?php

namespace App\Actions\Admin\Pages;

use App\Actions\Admin\Content\PurgeOrphanedContentImages;
use App\Models\Page;

class SavePage
{
    public function __construct(private readonly PurgeOrphanedContentImages $purgeOrphanedContentImages) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes, ?Page $page = null): Page
    {
        $replaced = $page?->body ?? '';

        $page ??= new Page;

        $page->fill($attributes)->save();

        ($this->purgeOrphanedContentImages)($replaced);

        return $page;
    }
}
