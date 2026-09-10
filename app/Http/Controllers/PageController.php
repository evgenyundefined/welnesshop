<?php

namespace App\Http\Controllers;

use App\Http\Resources\PageResource;
use App\Models\Page;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    public function __invoke(Page $page): PageResource
    {
        if (! $page->is_published) {
            throw new NotFoundHttpException;
        }

        return new PageResource($page);
    }
}
