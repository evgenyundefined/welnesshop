<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * Asset links are generated, not written by hand, so the scheme they carry is
 * a deployment setting: get it wrong and every stylesheet points at a port the
 * server is not listening on.
 */
class GeneratedUrlSchemeTest extends TestCase
{
    public function test_links_are_https_when_the_site_is_served_over_https(): void
    {
        $this->assertStringStartsWith('https://', $this->assetUrlWith('https://shop.example.ru'));
    }

    public function test_links_stay_http_when_the_site_is_served_over_http(): void
    {
        $url = $this->assetUrlWith('http://185.175.156.244');

        $this->assertStringStartsWith('http://', $url);
        $this->assertStringNotContainsString('https://', $url);
    }

    public function test_the_storefront_page_carries_links_the_same_host_can_serve(): void
    {
        config(['app.url' => 'http://185.175.156.244']);
        (new AppServiceProvider($this->app))->boot(config());

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('/build/assets/', $html);
        $this->assertStringNotContainsString('https://185.175.156.244', $html);
    }

    private function assetUrlWith(string $appUrl): string
    {
        config(['app.url' => $appUrl]);

        (new AppServiceProvider($this->app))->boot(config());

        return asset('build/assets/app.css');
    }
}
