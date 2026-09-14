<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Customer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    private const array CHECKOUT = [
        'contact_name' => 'Иван Петров',
        'contact_email' => 'ivan@example.com',
        'contact_phone' => '+79990000000',
        'shipping_address' => 'Москва, Тверская 1',
        'payment_method' => 'card',
        'delivery_method' => 'courier',
    ];

    private const array REGISTRATION = [
        'name' => 'Иван Петров',
        'email' => 'ivan@example.com',
        'phone' => '+79990000000',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ];

    #[DataProvider('invalidProductQueries')]
    public function test_product_listing_rejects_invalid_query_parameters(array $query): void
    {
        $this->getJson(route('api.products', $query))->assertUnprocessable();
    }

    public static function invalidProductQueries(): array
    {
        return [
            'unknown category' => [['category' => 'no-such-category']],
            'too long search' => [['search' => str_repeat('a', 256)]],
            'non boolean in_stock' => [['in_stock' => 'maybe']],
            'per_page below minimum' => [['per_page' => 0]],
            'per_page above maximum' => [['per_page' => 1000]],
            'non numeric per_page' => [['per_page' => 'all']],
            'unknown sort' => [['sort' => 'cheapest']],
        ];
    }

    public function test_listing_page_sizes_fall_back_to_configuration(): void
    {
        config()->set('shop.products_per_page', 3);
        config()->set('shop.max_products_per_page', 5);

        $this->getJson(route('api.products'))->assertOk()->assertJsonPath('meta.per_page', 3);
        $this->getJson(route('api.products', ['per_page' => 5]))->assertOk()->assertJsonPath('meta.per_page', 5);
        $this->getJson(route('api.products', ['per_page' => 6]))->assertUnprocessable();

        $this->actingAs(Customer::factory()->create());

        $this->getJson(route('api.orders.index'))->assertOk()->assertJsonPath('meta.per_page', 10);
        $this->getJson(route('api.orders.index', ['per_page' => 50]))->assertOk()->assertJsonPath('meta.per_page', 50);
        $this->getJson(route('api.orders.index', ['per_page' => 51]))->assertUnprocessable();
    }

    public function test_optional_filters_accept_empty_values(): void
    {
        $this->getJson('/api/products?category=&search=&sort=&in_stock=&per_page=')->assertOk();

        $this->actingAs(Customer::factory()->create());

        $this->getJson('/api/orders?per_page=')->assertOk()->assertJsonPath('meta.per_page', 10);
    }

    public function test_adding_a_line_validates_the_payload(): void
    {
        config()->set('shop.max_item_quantity', 7);

        $product = $this->makeProduct(['stock' => 100]);

        $this->postJson(route('api.cart.items.store'), ['quantity' => 1])->assertUnprocessable();
        $this->postJson(route('api.cart.items.store'), ['product_id' => 'x', 'quantity' => 1])->assertUnprocessable();
        $this->postJson(route('api.cart.items.store'), ['product_id' => 9999, 'quantity' => 1])->assertUnprocessable();
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id])->assertUnprocessable();
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 0])->assertUnprocessable();
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 8])->assertUnprocessable();

        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 7])->assertOk();
    }

    public function test_updating_a_line_validates_the_quantity(): void
    {
        config()->set('shop.max_item_quantity', 7);

        $product = $this->makeProduct(['stock' => 100]);

        $this->actingAs(Customer::factory()->create());
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);

        $item = CartItem::query()->where('product_id', $product->id)->sole();

        $this->patchJson(route('api.cart.items.update', $item), [])->assertUnprocessable();
        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 0])->assertUnprocessable();
        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 'two'])->assertUnprocessable();
        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 8])->assertUnprocessable();

        $this->patchJson(route('api.cart.items.update', $item), ['quantity' => 7])->assertOk();
    }

    #[DataProvider('invalidCheckoutPayloads')]
    public function test_checkout_rejects_invalid_payloads(array $override): void
    {
        $this->fillCart();

        $this->postJson(route('api.checkout'), [...self::CHECKOUT, ...$override])->assertUnprocessable();
    }

    public static function invalidCheckoutPayloads(): array
    {
        return [
            'short name' => [['contact_name' => 'И']],
            'long name' => [['contact_name' => str_repeat('a', 256)]],
            'malformed email' => [['contact_email' => 'ivan(at)example.com']],
            'long email' => [['contact_email' => str_repeat('a', 250).'@example.com']],
            'short phone' => [['contact_phone' => '+7']],
            'long phone' => [['contact_phone' => str_repeat('9', 33)]],
            'short address' => [['shipping_address' => 'Мск']],
            'long address' => [['shipping_address' => str_repeat('a', 1001)]],
            'long comment' => [['comment' => str_repeat('a', 1001)]],
            'unknown payment method' => [['payment_method' => 'bitcoin']],
            'unknown delivery method' => [['delivery_method' => 'teleport']],
        ];
    }

    #[DataProvider('requiredCheckoutFields')]
    public function test_checkout_requires_every_contact_field(string $field): void
    {
        $this->fillCart();

        $payload = self::CHECKOUT;
        unset($payload[$field]);

        $this->postJson(route('api.checkout'), $payload)->assertUnprocessable();
    }

    public static function requiredCheckoutFields(): array
    {
        return [
            'contact_name' => ['contact_name'],
            'contact_email' => ['contact_email'],
            'contact_phone' => ['contact_phone'],
            'shipping_address' => ['shipping_address'],
            'payment_method' => ['payment_method'],
            'delivery_method' => ['delivery_method'],
        ];
    }

    public function test_checkout_accepts_an_explicitly_empty_comment(): void
    {
        $this->fillCart();

        $this->postJson(route('api.checkout'), [...self::CHECKOUT, 'comment' => null])
            ->assertCreated()
            ->assertJsonPath('data.comment', null);
    }

    #[DataProvider('invalidRegistrationPayloads')]
    public function test_registration_rejects_invalid_payloads(array $override): void
    {
        $this->postJson(route('api.register'), [...self::REGISTRATION, ...$override])->assertUnprocessable();

        $this->assertDatabaseCount('customers', 0);
    }

    public static function invalidRegistrationPayloads(): array
    {
        return [
            'short name' => [['name' => 'И']],
            'long name' => [['name' => str_repeat('a', 256)]],
            'malformed email' => [['email' => 'ivan(at)example.com']],
            'long email' => [['email' => str_repeat('a', 250).'@example.com']],
            'long phone' => [['phone' => str_repeat('9', 33)]],
            'short password' => [['password' => 'abcd', 'password_confirmation' => 'abcd']],
            'long password' => [['password' => str_repeat('Aa1', 90), 'password_confirmation' => str_repeat('Aa1', 90)]],
        ];
    }

    #[DataProvider('requiredRegistrationFields')]
    public function test_registration_requires_every_mandatory_field(string $field): void
    {
        $payload = self::REGISTRATION;
        unset($payload[$field]);

        $this->postJson(route('api.register'), $payload)->assertUnprocessable();

        $this->assertDatabaseCount('customers', 0);
    }

    public static function requiredRegistrationFields(): array
    {
        return [
            'name' => ['name'],
            'email' => ['email'],
            'password' => ['password'],
        ];
    }

    public function test_registration_only_asks_the_password_to_be_long_enough(): void
    {
        $this->postJson(route('api.register'), [
            ...self::REGISTRATION,
            'password' => 'abcd',
            'password_confirmation' => 'abcd',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        // Five plain lowercase letters are enough: no case or digit is demanded.
        $this->postJson(route('api.register'), [
            ...self::REGISTRATION,
            'password' => 'parol',
            'password_confirmation' => 'parol',
        ])->assertCreated();
    }

    public function test_registration_accepts_an_explicitly_empty_phone(): void
    {
        $this->postJson(route('api.register'), [...self::REGISTRATION, 'phone' => null])
            ->assertCreated()
            ->assertJsonPath('data.phone', null);
    }

    #[DataProvider('invalidLoginPayloads')]
    public function test_login_rejects_invalid_payloads(array $override): void
    {
        Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $this->postJson(route('api.login'), [
            'email' => 'buyer@example.com',
            'password' => 'Password1',
            ...$override,
        ])->assertUnprocessable();
    }

    public static function invalidLoginPayloads(): array
    {
        return [
            'malformed email' => [['email' => 'buyer(at)example.com']],
            'long email' => [['email' => str_repeat('a', 250).'@example.com']],
            'long password' => [['password' => str_repeat('a', 256)]],
            'non boolean remember' => [['remember' => 'sometimes']],
        ];
    }

    #[DataProvider('requiredLoginFields')]
    public function test_login_requires_credentials(string $field): void
    {
        Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $payload = ['email' => 'buyer@example.com', 'password' => 'Password1'];
        unset($payload[$field]);

        $this->postJson(route('api.login'), $payload)->assertUnprocessable();
    }

    public static function requiredLoginFields(): array
    {
        return [
            'email' => ['email'],
            'password' => ['password'],
        ];
    }

    public function test_login_accepts_an_explicitly_empty_remember_flag(): void
    {
        $customer = Customer::factory()->create(['email' => 'buyer@example.com', 'password' => 'Password1']);

        $this->postJson(route('api.login'), [
            'email' => 'buyer@example.com',
            'password' => 'Password1',
            'remember' => null,
        ])->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    private function fillCart(): void
    {
        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAs(Customer::factory()->create());
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1]);
    }
}
