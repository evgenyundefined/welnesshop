<?php

namespace App\Enums;

enum PageVisibility: string
{
    case Draft = 'draft';
    case Unlisted = 'unlisted';
    case Published = 'published';

    public function isReachableByLink(): bool
    {
        return $this !== self::Draft;
    }

    public function isListedInMenus(): bool
    {
        return $this === self::Published;
    }
}
