<?php

namespace Tests\Feature;

use App\Jobs\AnnounceOrder;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Notifications\TelegramChat;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.api_url', 'https://api.telegram.org');
        config()->set('services.telegram.bot_token', '123:token');
        config()->set('services.telegram.chat_id', '604336663');
    }

    private function telegram(): TelegramChat
    {
        return $this->app->make(TelegramChat::class);
    }

    public function test_a_message_reaches_the_configured_chat(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->assertSame('', $this->telegram()->deliver('привет'));

        Http::assertSent(static fn ($request): bool => $request->url() === 'https://api.telegram.org/bot123:token/sendMessage'
            && $request['chat_id'] === '604336663'
            && $request['text'] === 'привет');
    }

    public function test_an_unreachable_api_is_reported_not_swallowed(): void
    {
        // What a blocked host looks like from the inside: no answer at all.
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->assertStringContainsString('Telegram недоступен', $this->telegram()->deliver('привет'));
    }

    public function test_telegram_own_refusal_is_quoted(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);

        $this->assertStringContainsString('chat not found', $this->telegram()->deliver('привет'));
    }

    public function test_a_missing_chat_id_is_named(): void
    {
        config()->set('services.telegram.chat_id', null);

        $this->assertSame('не задан TELEGRAM_CHAT_ID', $this->telegram()->deliver('привет'));
    }

    public function test_a_mirror_is_used_when_the_api_is_moved(): void
    {
        config()->set('services.telegram.api_url', 'https://tg.example.com');
        Http::fake(['tg.example.com/*' => Http::response(['ok' => true])]);

        $this->assertSame('', $this->telegram()->deliver('привет'));

        Http::assertSent(static fn ($request): bool => str_starts_with($request->url(), 'https://tg.example.com/bot'));
    }

    public function test_known_chats_are_listed_so_the_id_can_be_read_off(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
            ['message' => ['chat' => ['id' => 604336663, 'first_name' => 'Евгений', 'type' => 'private']]],
            ['message' => ['chat' => ['id' => -1001, 'title' => 'Заказы', 'type' => 'group']]],
            ['message' => ['chat' => ['id' => 604336663, 'first_name' => 'Евгений', 'type' => 'private']]],
        ]])]);

        ['error' => $error, 'chats' => $chats] = $this->telegram()->discoverChats();

        $this->assertSame('', $error);
        $this->assertSame(
            [['id' => '604336663', 'title' => 'Евгений'], ['id' => '-1001', 'title' => 'Заказы']],
            $chats,
        );
    }

    public function test_the_command_says_what_is_wrong_and_offers_the_ids(): void
    {
        config()->set('services.telegram.chat_id', null);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => [
            ['message' => ['chat' => ['id' => 604336663, 'first_name' => 'Евгений', 'type' => 'private']]],
        ]])]);

        $this->artisan('telegram:test')
            ->expectsOutputToContain('Не задан TELEGRAM_CHAT_ID')
            ->expectsOutputToContain('604336663')
            ->assertFailed();
    }

    public function test_the_command_confirms_a_working_bot(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->artisan('telegram:test')
            ->expectsOutputToContain('Сообщение отправлено в чат 604336663')
            ->assertSuccessful();
    }

    public function test_checkout_does_not_wait_for_the_chat(): void
    {
        Bus::fake();
        Mail::fake();

        $customer = Customer::factory()->create();
        $product = Product::factory()
            ->for(Category::factory()->create(['min_order_quantity' => 1]))
            ->create(['stock' => 50, 'price_minor' => 1_000_00]);

        $this->actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->postJson('/api/checkout', [
            'contact_name' => 'Иван Петров',
            'contact_email' => 'ivan@example.com',
            'contact_phone' => '+7 912 345 67 89',
            'delivery_method' => 'courier',
            'shipping_address' => 'Москва, Тверская 1',
            'payment_method' => 'on_agreement',
        ])->assertCreated();

        // The buyer's response must not be behind a network call to Telegram:
        // a blocked host would hold the button for the whole timeout.
        Bus::assertDispatched(AnnounceOrder::class);
        Http::assertNothingSent();
    }
}
