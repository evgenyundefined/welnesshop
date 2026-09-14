<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Routing\UrlGenerator;

/**
 * ЮKassa, redirect flow: the shop opens a payment, sends the buyer to the
 * confirmation page, and learns the outcome from the webhook rather than from
 * the browser coming back.
 */
final readonly class YooKassaGateway implements PaymentGateway
{
    public const string PROVIDER = 'yookassa';

    public function __construct(
        private Http $http,
        private Config $config,
        private UrlGenerator $url,
    ) {}

    public static function isConfigured(Config $config): bool
    {
        // Read untyped: an absent variable is null, and string() would throw
        // on the very case this method exists to answer.
        return (string) $config->get('services.yookassa.shop_id') !== ''
            && (string) $config->get('services.yookassa.secret_key') !== '';
    }

    public function isLive(): bool
    {
        return true;
    }

    public function createPayment(Order $order): PaymentIntent
    {
        // Keyed by the order, so a double click or a retried request opens the
        // same payment instead of charging twice.
        $payment = $this->send(fn (PendingRequest $request): Response => $request
            ->withHeader('Idempotence-Key', self::PROVIDER.'-order-'.$order->id)
            ->post('/payments', [
                'amount' => [
                    'value' => number_format($order->total_minor / 100, 2, '.', ''),
                    'currency' => $order->currency,
                ],
                'capture' => true,
                'description' => "Заказ {$order->number}",
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $this->url->to("/orders/{$order->number}/payment"),
                ],
                'metadata' => ['order_number' => $order->number],
            ]));

        return $this->intentFrom($payment, $order);
    }

    /**
     * The webhook carries a payment object, but anyone can post one. Only what
     * the acquirer says over an authenticated call is believed.
     *
     * @return array<string, mixed>
     */
    public function fetchPayment(string $id): array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->get("/payments/{$id}"));
    }

    /** @param array<string, mixed> $payment */
    public function intentFrom(array $payment, Order $order): PaymentIntent
    {
        $status = self::statusOf((string) ($payment['status'] ?? ''));

        return new PaymentIntent(
            status: $status,
            provider: self::PROVIDER,
            message: match ($status) {
                PaymentStatus::Succeeded => 'Оплата получена.',
                PaymentStatus::Failed => 'Платёж отменён платёжной системой.',
                default => 'Платёж создан, перенаправляем на страницу оплаты.',
            },
            confirmationUrl: $payment['confirmation']['confirmation_url'] ?? null,
            externalId: isset($payment['id']) ? (string) $payment['id'] : null,
        );
    }

    public static function statusOf(string $status): PaymentStatus
    {
        return match ($status) {
            'succeeded' => PaymentStatus::Succeeded,
            'canceled' => PaymentStatus::Failed,
            default => PaymentStatus::AwaitingGateway,
        };
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        try {
            $response = $call($this->request());
        } catch (ConnectionException $e) {
            throw PaymentFailed::because('Платёжная система не отвечает: '.$e->getMessage());
        }

        if ($response->failed()) {
            $description = $response->json('description');

            throw PaymentFailed::because(is_string($description) && $description !== ''
                ? "Платёжная система отклонила запрос: {$description}"
                : "Платёжная система ответила ошибкой {$response->status()}.");
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->config->string('services.yookassa.base_url'))
            ->timeout($this->config->integer('services.yookassa.timeout'))
            ->withBasicAuth(
                (string) $this->config->get('services.yookassa.shop_id'),
                (string) $this->config->get('services.yookassa.secret_key'),
            )
            ->acceptJson();
    }
}
