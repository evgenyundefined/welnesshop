<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\UrlGenerator;

class RobotsController extends Controller
{
    public function __construct(private readonly UrlGenerator $url) {}

    /**
     * Served by the application rather than as a file so the sitemap line
     * carries the domain the site is actually running on.
     */
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /orders',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /api/',
            '',
            'Sitemap: '.$this->url->to('/sitemap.xml'),
            '',
        ];

        return new Response(implode("\n", $lines), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
