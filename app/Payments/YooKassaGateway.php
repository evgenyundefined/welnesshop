<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;

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
                    'currency' => $order->currency->value,
                ],
                'capture' => true,
                'description' => "Заказ {$order->number}",
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $this->url->to("/orders/{$order->number}/payment"),
                ],
                'metadata' => ['order_number' => $order->number],
                ...$this->receipt($order),
            ]));

        return $this->intentFrom($payment, $order);
    }

    /**
     * A shop with fiscalisation turned on cannot take a payment without one:
     * ЮKassa answers "Receipt is missing or illegal". The lines are the order's
     * own, and delivery is a line of its own, so the total on the receipt is
     * the total being charged.
     *
     * @return array<string, mixed>
     */
    private function receipt(Order $order): array
    {
        if (! $this->config->boolean('services.yookassa.receipt')) {
            return [];
        }

        $items = $order->items
            ->map(fn (OrderItem $item): array => $this->receiptItem(
                $item->product_name,
                $item->unit_price_minor,
                $item->quantity,
                'commodity',
                $order->currency->value,
            ))
            ->all();

        if ($order->delivery_cost_minor > 0) {
            $items[] = $this->receiptItem(
                'Доставка — '.$order->delivery_method->label(),
                $order->delivery_cost_minor,
                1,
                'service',
                $order->currency->value,
            );
        }

        return ['receipt' => array_filter([
            'customer' => array_filter([
                'email' => $order->contact_email,
                // The register wants E.164 without separators.
                'phone' => preg_replace('/\D/', '', $order->contact_phone),
            ]),
            'items' => $items,
            'tax_system_code' => $this->config->get('services.yookassa.tax_system_code'),
        ], static fn (mixed $value): bool => $value !== null)];
    }

    /** @return array<string, mixed> */
    private function receiptItem(string $description, int $unitMinor, int $quantity, string $subject, string $currency): array
    {
        return [
            'description' => Str::limit($description, 128, ''),
            'quantity' => $quantity,
            'amount' => [
                'value' => number_format($unitMinor / 100, 2, '.', ''),
                'currency' => $currency,
            ],
            'vat_code' => $this->config->integer('services.yookassa.vat_code'),
            'payment_mode' => 'full_prepayment',
            'payment_subject' => $subject,
            'measure' => 'piece',
        ];
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
