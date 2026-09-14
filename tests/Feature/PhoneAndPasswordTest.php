<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Providers\AppServiceProvider;
use App\Support\PhoneNumber;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneAndPasswordTest extends TestCase
{
    private const array REGISTRATION = [
        'name' => 'Иван Петров',
        'email' => 'ivan@example.com',
        'password' => 'parol',
        'password_confirmation' => 'parol',
    ];

    #[DataProvider('phonesPeopleType')]
    public function test_a_phone_is_stored_in_one_shape_however_it_was_typed(string $typed): void
    {
        $this->postJson(route('api.register'), [...self::REGISTRATION, 'phone' => $typed])
            ->assertCreated()
            ->assertJsonPath('data.phone', '+7 999 123 45 67');
    }

    /** @return array<string, array{0: string}> */
    public static function phonesPeopleType(): array
    {
        return [
            'masked' => ['+7 999 123 45 67'],
            'bare digits' => ['79991234567'],
            'leading eight' => ['89991234567'],
            'brackets and dashes' => ['+7 (999) 123-45-67'],
            'ten digits' => ['9991234567'],
            'padded' => ['  8 999 123 45 67  '],
        ];
    }

    #[DataProvider('phonesThatAreNotPhones')]
    public function test_something_that_is_not_a_russian_number_is_refused(string $typed): void
    {
        $this->postJson(route('api.register'), [...self::REGISTRATION, 'phone' => $typed])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertSame(0, Customer::query()->count());
    }

    /** @return array<string, array{0: string}> */
    public static function phonesThatAreNotPhones(): array
    {
        return [
            'too short' => ['999123'],
            'too long' => ['799912345678'],
            'letters' => ['телефон'],
            'foreign' => ['+44 20 7123 4567'],
        ];
    }

    public function test_an_empty_phone_stays_empty(): void
    {
        $this->postJson(route('api.register'), [...self::REGISTRATION, 'phone' => ''])
            ->assertCreated()
            ->assertJsonPath('data.phone', null);
    }

    public function test_the_checkout_normalises_the_contact_phone_too(): void
    {
        $customer = Customer::factory()->create(['phone' => '+7 999 123 45 67']);
        $this->actingAs($customer, 'web');
        $product = $this->makeProduct(['stock' => 5]);
        $this->postJson(route('api.cart.items.store'), ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->postJson(route('api.checkout'), [
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '8(999)123-45-67',
            'delivery_method' => 'courier',
            'shipping_address' => 'Москва, Тверская 1',
            'payment_method' => 'card',
        ])
            ->assertCreated()
            ->assertJsonPath('data.contact_phone', '+7 999 123 45 67');
    }

    public function test_an_admin_editing_a_customer_gets_the_same_treatment(): void
    {
        $this->signInAdmin();
        $customer = Customer::factory()->create();

        $this->putJson(route('admin.api.customers.update', $customer), [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'phone' => '89991234567',
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', '+7 999 123 45 67');
    }

    public function test_five_plain_characters_are_a_good_enough_password(): void
    {
        $this->postJson(route('api.register'), [
            ...self::REGISTRATION,
            'password' => 'парол',
            'password_confirmation' => 'парол',
        ])->assertCreated();

        $this->postJson(route('api.login'), ['email' => 'ivan@example.com', 'password' => 'парол'])->assertOk();
    }

    public function test_the_minimum_length_comes_from_configuration(): void
    {
        config()->set('shop.password_min_length', 10);
        $this->app->make(Application::class)
            ->make(AppServiceProvider::class, ['app' => $this->app])
            ->boot($this->app->make(Repository::class));

        $this->postJson(route('api.register'), [
            ...self::REGISTRATION,
            'password' => 'parol1234',
            'password_confirmation' => 'parol1234',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_the_formatter_leaves_unrecognisable_input_alone(): void
    {
        $this->assertNull(PhoneNumber::format(null));
        $this->assertNull(PhoneNumber::format('   '));
        $this->assertSame('не телефон', PhoneNumber::format('  не телефон  '));
    }
}
