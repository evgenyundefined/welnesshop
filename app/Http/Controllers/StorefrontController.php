<?php

namespace App\Http\Controllers;

use App\Actions\Seo\ResolvePageMeta;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

class StorefrontController extends Controller
{
    public function __construct(private readonly ViewFactory $view) {}

    public function __invoke(ResolvePageMeta $resolvePageMeta, ?string $path = null): View
    {
        return $this->view->make('storefront', ['meta' => $resolvePageMeta($path ?? '')]);
    }
}
