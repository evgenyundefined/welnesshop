<?php

namespace App\Actions\Admin\Site;

use App\Delivery\Cdek\CdekClient;
use App\Payments\PaymentGateway;
use App\Payments\YooKassaGateway;
use Illuminate\Config\Repository as Config;

class DescribeIntegrations
{
    public function __construct(
        private readonly Config $config,
        private readonly CdekClient $cdek,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Which contour each integration is talking to. An owner who cannot see
     * this finds out from a tariff that looks wrong or a payment that never
     * arrives on the statement.
     *
     * @return array<string, array<string, mixed>>
     */
    public function __invoke(): array
    {
        return [
            'cdek' => [
                'enabled' => $this->cdek->isConfigured(),
                'test' => $this->config->boolean('services.cdek.test'),
                'endpoint' => $this->config->string('services.cdek.base_url'),
            ],
            'payments' => [
                'enabled' => $this->gateway->isLive(),
                // ЮKassa marks a test shop's key with the prefix itself.
                'test' => str_starts_with((string) $this->config->get('services.yookassa.secret_key'), 'test_'),
                'provider' => $this->gateway->isLive() ? YooKassaGateway::PROVIDER : null,
            ],
        ];
    }
}
