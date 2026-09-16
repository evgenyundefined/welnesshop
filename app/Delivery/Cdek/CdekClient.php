<?php

namespace App\Delivery\Cdek;

use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The parts of CDEK API v2 the shop needs: what a city is called, where its
 * pickup points are, and what the carrier charges for a parcel.
 */
class CdekClient
{
    private const TOKEN_CACHE_KEY = 'cdek.access_token';

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    public function isConfigured(): bool
    {
        // Read untyped: an absent variable is null, and string() would throw
        // on the very case this method exists to answer.
        return (string) $this->config->get('services.cdek.account') !== ''
            && (string) $this->config->get('services.cdek.password') !== ''
            && $this->config->get('services.cdek.from_city_code') !== null;
    }

    /**
     * Configured is not the same as offered: the shop can switch the carrier
     * off for a while without losing its credentials.
     */
    public function isEnabled(): bool
    {
        return $this->config->boolean('shop.integrations.cdek') && $this->isConfigured();
    }

    /** @return Collection<int, CdekCity> */
    public function cities(string $query, int $limit = 10): Collection
    {
        $payload = $this->get('/location/cities', [
            'country_codes' => 'RU',
            'city' => $query,
            'size' => $limit,
        ]);

        return collect($payload)
            ->map(fn (array $city): CdekCity => CdekCity::fromResponse($city))
            ->filter(fn (CdekCity $city): bool => $city->code > 0)
            ->values();
    }

    /**
     * The city behind a code the buyer picked earlier, so the order stores a
     * name the carrier itself uses rather than one the browser sent.
     */
    public function city(int $code): ?CdekCity
    {
        $payload = $this->get('/location/cities', ['code' => $code, 'size' => 1]);

        return $payload === [] ? null : CdekCity::fromResponse($payload[0]);
    }

    /** @return Collection<int, CdekPoint> */
    public function points(int $cityCode): Collection
    {
        $payload = $this->get('/deliverypoints', [
            'city_code' => $cityCode,
            'country_code' => 'RU',
            'is_handout' => 'true',
        ]);

        return collect($payload)
            ->map(fn (array $point): CdekPoint => CdekPoint::fromResponse($point))
            ->filter(fn (CdekPoint $point): bool => $point->code !== '' && $point->address !== '')
            ->values();
    }

    /**
     * Every tariff the carrier offers for this direction and weight. The
     * caller decides which of them belong to the destination the buyer chose.
     *
     * @return Collection<int, CdekTariff>
     */
    public function tariffs(int $toCityCode, int $weightGrams, ?string $deliveryPoint = null): Collection
    {
        $package = $this->config->array('services.cdek.package');

        $payload = $this->post('/calculator/tarifflist', array_filter([
            'type' => 1,
            'currency' => 1,
            'lang' => 'rus',
            'shipment_point' => $this->config->get('services.cdek.shipment_point'),
            'delivery_point' => $deliveryPoint,
            'from_location' => ['code' => $this->config->integer('services.cdek.from_city_code')],
            'to_location' => ['code' => $toCityCode],
            'packages' => [[
                'weight' => $weightGrams,
                'length' => $package['length'],
                'width' => $package['width'],
                'height' => $package['height'],
            ]],
        ], static fn (mixed $value): bool => $value !== null));

        $this->refuseOnErrors($payload);

        return collect($payload['tariff_codes'] ?? [])
            ->map(fn (array $tariff): CdekTariff => CdekTariff::fromResponse($tariff))
            ->filter(fn (CdekTariff $tariff): bool => $tariff->code > 0)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function get(string $path, array $query): array
    {
        /** @var array<int, array<string, mixed>> $payload */
        $payload = $this->send(fn (PendingRequest $request): Response => $request->get($path, $query));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->post($path, $body));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<mixed>
     */
    private function send(callable $call): array
    {
        if (! $this->isConfigured()) {
            throw CdekUnavailable::notConfigured();
        }

        if (! $this->isEnabled()) {
            throw CdekUnavailable::disabled();
        }

        try {
            $response = $call($this->request()->withToken($this->token()));

            // An expired token reads as an ordinary 401, and the cache cannot
            // know the carrier revoked it, so one retry on a fresh one.
            if ($response->status() === 401) {
                $this->cache->forget(self::TOKEN_CACHE_KEY);
                $response = $call($this->request()->withToken($this->token()));
            }
        } catch (ConnectionException $e) {
            throw CdekUnavailable::because('Служба доставки не отвечает: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw CdekUnavailable::because("Служба доставки ответила ошибкой {$response->status()}.");
        }

        /** @var array<mixed> $payload */
        $payload = $response->json() ?? [];

        return $payload;
    }

    private function token(): string
    {
        /** @var string $token */
        $token = $this->cache->remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            try {
                $response = $this->request()->asForm()->post('/oauth/token?parameters', [
                    'grant_type' => 'client_credentials',
                    'client_id' => (string) $this->config->get('services.cdek.account'),
                    'client_secret' => (string) $this->config->get('services.cdek.password'),
                ]);
            } catch (Throwable $e) {
                throw CdekUnavailable::because('Не удалось получить токен СДЭК: '.$e->getMessage());
            }

            $token = $response->json('access_token');

            if ($response->failed() || ! is_string($token) || $token === '') {
                throw CdekUnavailable::because('СДЭК отклонил учётные данные интеграции.');
            }

            return $token;
        });

        return $token;
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->config->string('services.cdek.base_url'))
            ->timeout($this->config->integer('services.cdek.timeout'))
            ->acceptJson();
    }

    /** @param array<string, mixed> $payload */
    private function refuseOnErrors(array $payload): void
    {
        $errors = $payload['errors'] ?? [];

        if ($errors === []) {
            return;
        }

        $message = collect($errors)
            ->map(static fn (array $error): string => (string) ($error['message'] ?? $error['code'] ?? ''))
            ->filter()
            ->implode('; ');

        throw CdekUnavailable::because($message === '' ? 'Служба доставки отказала в расчёте.' : $message);
    }
}
