<?php

namespace App\Actions\Site;

use App\Models\Page;
use Illuminate\Database\Eloquent\Collection;

class ListPages
{
    /**
     * @return Collection<int, Page>
     */
    public function __invoke(): Collection
    {
        return Page::query()
            ->published()
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'slug', 'title']);
    }
}
