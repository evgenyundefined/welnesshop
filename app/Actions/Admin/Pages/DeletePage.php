<?php

namespace App\Actions\Admin\Pages;

use App\Actions\Admin\Content\PurgeOrphanedContentImages;
use App\Models\Page;

class DeletePage
{
    public function __construct(private readonly PurgeOrphanedContentImages $purgeOrphanedContentImages) {}

    public function __invoke(Page $page): void
    {
        $body = $page->body ?? '';

        $page->delete();

        ($this->purgeOrphanedContentImages)($body);
    }
}
