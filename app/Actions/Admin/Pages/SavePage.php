<?php

namespace App\Actions\Admin\Pages;

use App\Models\Page;

class SavePage
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes, ?Page $page = null): Page
    {
        $page ??= new Page;

        $page->fill($attributes)->save();

        return $page;
    }
}
