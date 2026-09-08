<?php

namespace Tests\Feature;

use App\Enums\Guard;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A block is only worth as much as the narrowest way around it, so every route
 * that hands a customer their account back is checked here.
 */
class CustomerBlockingTest extends TestCase
{
    private const PASSWORD = 'Password1';

    public function test_a_blocked_customer_cannot_sign_in_and_gets_no_session(): void
    {
        $customer = $this->blockedCustomer();

        $response = $this->postJson(route('api.login'), [
            'email' => $customer->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertForbidden()->assertJsonPath('message', 'Аккаунт заблокирован. Обратитесь в поддержку.');

        $this->carrySession($response);

        $this->getJson(route('api.me'))->assertUnauthorized();
    }

    public function test_a_blocked_customer_cannot_sign_in_with_remember_me_either(): void
    {
        $customer = $this->blockedCustomer();

        $response = $this->postJson(route('api.login'), [
            'email' => $customer->email,
            'password' => self::PASSWORD,
            'remember' => true,
        ])->assertForbidden();

        $this->assertNull($this->recallerFrom($response));
    }

    public function test_an_existing_session_stops_working_the_moment_the_customer_is_blocked(): void
    {
        $customer = $this->customer();

        $this->carrySession($this->signIn($customer));

        $this->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $customer->id);

        $this->block($customer);

        $blockedResponse = $this->getJson(route('api.me'))->assertForbidden();

        $this->carrySession($blockedResponse);
        $this->unblock($customer);

        $this->getJson(route('api.me'))->assertUnauthorized();
    }

    #[DataProvider('customerEndpoints')]
    public function test_a_blocked_customer_is_turned_away_from(string $method, string $route): void
    {
        $customer = $this->customer();

        $this->carrySession($this->signIn($customer));

        $this->block($customer);

        $this->json($method, route($route))->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function customerEndpoints(): array
    {
        return [
            'me' => ['GET', 'api.me'],
            'logout' => ['POST', 'api.logout'],
            'checkout' => ['POST', 'api.checkout'],
            'orders' => ['GET', 'api.orders.index'],
            'cart' => ['GET', 'api.cart.show'],
            'cart items' => ['POST', 'api.cart.items.store'],
            'catalog' => ['GET', 'api.products'],
        ];
    }

    public function test_a_remember_me_cookie_cannot_resurrect_a_blocked_customer(): void
    {
        $customer = $this->customer();

        $login = $this->postJson(route('api.login'), [
            'email' => $customer->email,
            'password' => self::PASSWORD,
            'remember' => true,
        ])->assertOk();

        $recaller = $this->recallerFrom($login);
        $this->assertNotNull($recaller);

        $this->forgetBrowser();
        $this->withCookie($this->recallerName(), $recaller);

        $this->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $customer->id);

        $this->block($customer);

        $this->forgetBrowser();
        $this->withCookie($this->recallerName(), $recaller);

        $this->getJson(route('api.me'))->assertForbidden();
    }

    public function test_a_blocked_customer_cannot_pay_an_order_placed_before_the_block(): void
    {
        $customer = $this->customer();
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder($customer, [$product->id => 1]);

        $this->carrySession($this->signIn($customer));

        $this->block($customer);

        $this->postJson(route('api.orders.pay', $order))->assertForbidden();

        // Being turned away also ends the session, so the follow-up is a guest.
        $this->getJson(route('api.orders.show', $order))->assertUnauthorized();

        $this->assertSame($order->payment_status, Order::query()->find($order->id)->payment_status);
    }

    public function test_a_blocked_customer_cannot_register_the_same_email_again(): void
    {
        $customer = $this->blockedCustomer();

        $this->postJson(route('api.register'), [
            'name' => 'Иван Петров',
            'email' => $customer->email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, Customer::query()->where('email', $customer->email)->count());
    }

    public function test_a_block_applies_to_one_account_only(): void
    {
        $blocked = $this->blockedCustomer();
        $other = $this->customer();

        $this->carrySession($this->signIn($other));

        $this->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $other->id);

        $this->assertTrue($blocked->refresh()->isBlocked());
        $this->assertFalse($other->refresh()->isBlocked());
    }

    public function test_unblocking_lets_the_customer_back_in(): void
    {
        $customer = $this->blockedCustomer();

        $this->postJson(route('api.login'), ['email' => $customer->email, 'password' => self::PASSWORD])
            ->assertForbidden();

        $this->unblock($customer);
        $this->forgetBrowser();

        $this->carrySession(
            $this->postJson(route('api.login'), ['email' => $customer->email, 'password' => self::PASSWORD])
                ->assertOk(),
        );

        $this->getJson(route('api.me'))->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    public function test_blocking_a_customer_does_not_touch_the_admin_panel(): void
    {
        $admin = Admin::factory()->create();
        $customer = $this->blockedCustomer();

        $this->actingAs($admin, Guard::Admin->value);
        $this->app['auth']->shouldUse(Guard::Customer->value);

        $this->getJson(route('admin.api.customers.show', $customer))
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true);
    }

    public function test_a_blocked_customer_keeps_their_cart_rows_untouched(): void
    {
        $customer = $this->customer();
        $product = $this->makeProduct(['stock' => 5]);

        $this->carrySession($this->signIn($customer));

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();

        $this->block($customer);

        $this->deleteJson(route('api.cart.clear'))->assertForbidden();

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['password' => self::PASSWORD]);
    }

    private function blockedCustomer(): Customer
    {
        $customer = $this->customer();
        $this->block($customer);

        return $customer;
    }

    private function signIn(Customer $customer): TestResponse
    {
        return $this->postJson(route('api.login'), [
            'email' => $customer->email,
            'password' => self::PASSWORD,
        ])->assertOk();
    }

    /**
     * Blocking happens through the real admin endpoint so the panel and the
     * storefront are proven to agree on what "blocked" means.
     */
    private function block(Customer $customer): void
    {
        $this->asAdmin(fn () => $this->postJson(route('admin.api.customers.block', $customer))->assertOk());
    }

    private function unblock(Customer $customer): void
    {
        $this->asAdmin(fn () => $this->deleteJson(route('admin.api.customers.unblock', $customer))->assertOk());
    }

    private function asAdmin(callable $callback): void
    {
        $cookies = $this->defaultCookies;
        $this->defaultCookies = [];

        $this->signInAdmin();
        $callback();

        $this->app['auth']->forgetGuards();
        $this->defaultCookies = $cookies;
    }

    private function forgetBrowser(): void
    {
        $this->defaultCookies = [];
        $this->app['auth']->forgetGuards();
    }

    private function recallerName(): string
    {
        $guard = Auth::guard(Guard::Customer->value);

        return $guard instanceof SessionGuard ? $guard->getRecallerName() : '';
    }

    private function recallerFrom(TestResponse $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $this->recallerName() && $cookie->getValue() !== null) {
                return (string) $cookie->getValue();
            }
        }

        return null;
    }
}
