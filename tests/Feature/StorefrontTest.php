<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorefrontTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_spa_shell_is_served_for_storefront_routes(): void
    {
        foreach (['/', '/cart', '/products/epitalon', '/orders/WLN-1/payment'] as $path) {
            $this->get($path)->assertOk()->assertSee('id="app"', false);
        }
    }

    public function test_unknown_api_routes_do_not_fall_through_to_the_spa(): void
    {
        $this->getJson('/api/unknown')->assertNotFound();
        $this->getJson('/api')->assertNotFound();
    }
}
