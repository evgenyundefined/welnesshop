<?php

namespace Tests;

use App\Actions\Orders\RecordOrder;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * The suite must not depend on whoever ran it having credentials in their
     * .env: every test starts with both integrations switched off and turns on
     * the one it is about.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.yookassa.shop_id', null);
        config()->set('services.yookassa.secret_key', null);
        config()->set('services.cdek.account', null);
        config()->set('services.cdek.password', null);
    }

    protected function makeProduct(array $attributes = []): Product
    {
        return Product::factory()->for(Category::factory())->create($attributes);
    }

    /**
     * @param  array<int, int>  $quantities  product id => quantity
     * @param  array<string, mixed>  $details
     */
    protected function makeOrder(Customer $customer, array $quantities, array $details = []): Order
    {
        return $this->app->make(RecordOrder::class)($customer, $quantities, [
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+79990000000',
            'shipping_address' => 'Москва, Тверская 1',
            'comment' => null,
            'payment_method' => PaymentMethod::Card,
            'delivery_method' => DeliveryMethod::Courier,
            ...$details,
        ]);
    }

    protected function signInAdmin(?Admin $admin = null): Admin
    {
        $admin ??= Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        // Signing in on a named guard makes it the default one, which the real
        // application never does -- the admin routes name their guard instead.
        $this->app['auth']->shouldUse('web');

        return $admin;
    }

    /**
     * Carries the session cookie from a response into the following requests and
     * forgets the resolved guards, so the next request has to rebuild both the
     * cart and the identity from that cookie alone -- the way a browser does.
     */
    protected function carrySession(TestResponse $response): TestResponse
    {
        return $this->carryCookie($response, config()->string('session.cookie'));
    }

    protected function carryCookie(TestResponse $response, string $name): TestResponse
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                $this->withCookie($name, (string) $cookie->getValue());
            }
        }

        $this->app['auth']->forgetGuards();

        return $response;
    }
}
