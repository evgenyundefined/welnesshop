<?php

namespace App\Http\Controllers;

use App\Actions\Seo\BuildSitemap;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(private readonly ViewFactory $view) {}

    public function __invoke(BuildSitemap $buildSitemap): Response
    {
        return new Response(
            $this->view->make('sitemap', ['entries' => $buildSitemap()])->render(),
            Response::HTTP_OK,
            ['Content-Type' => 'application/xml; charset=utf-8'],
        );
    }
}
