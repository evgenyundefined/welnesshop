<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class IntegrationContourTest extends TestCase
{
    public function test_the_test_switch_points_cdek_at_the_training_contour(): void
    {
        $this->assertSame('https://api.edu.cdek.ru/v2', $this->cdekEndpointWith(['CDEK_TEST' => 'true']));
        $this->assertSame('https://api.cdek.ru/v2', $this->cdekEndpointWith(['CDEK_TEST' => 'false']));
        $this->assertSame('https://api.cdek.ru/v2', $this->cdekEndpointWith([]));
    }

    public function test_an_explicit_address_still_wins_so_a_local_stub_can_be_used(): void
    {
        $this->assertSame(
            'http://127.0.0.1:8099/v2',
            $this->cdekEndpointWith(['CDEK_TEST' => 'true', 'CDEK_BASE_URL' => 'http://127.0.0.1:8099/v2/']),
        );
    }

    public function test_the_admin_is_told_which_contour_each_integration_talks_to(): void
    {
        $this->signInAdmin();

        config()->set('shop.integrations.cdek', true);
        config()->set('shop.integrations.online_payment', true);
        config()->set('services.cdek.account', 'account');
        config()->set('services.cdek.password', 'secret');
        config()->set('services.cdek.from_city_code', 44);
        config()->set('services.cdek.test', true);
        config()->set('services.cdek.base_url', 'https://api.edu.cdek.ru/v2');
        config()->set('services.yookassa.shop_id', '123456');
        config()->set('services.yookassa.secret_key', 'test_nZtcU');

        $this->getJson(route('admin.api.site.show'))
            ->assertOk()
            ->assertJsonPath('integrations.cdek.enabled', true)
            ->assertJsonPath('integrations.cdek.test', true)
            ->assertJsonPath('integrations.cdek.endpoint', 'https://api.edu.cdek.ru/v2')
            ->assertJsonPath('integrations.payments.enabled', true)
            ->assertJsonPath('integrations.payments.test', true)
            ->assertJsonPath('integrations.payments.provider', 'yookassa');
    }

    public function test_a_live_key_is_reported_as_live(): void
    {
        $this->signInAdmin();

        config()->set('shop.integrations.online_payment', true);
        config()->set('services.yookassa.shop_id', '123456');
        config()->set('services.yookassa.secret_key', 'live_nZtcU');

        $this->getJson(route('admin.api.site.show'))
            ->assertOk()
            ->assertJsonPath('integrations.payments.test', false)
            ->assertJsonPath('integrations.payments.enabled', true);
    }

    public function test_switched_off_integrations_say_so(): void
    {
        $this->signInAdmin();

        $this->getJson(route('admin.api.site.show'))
            ->assertOk()
            ->assertJsonPath('integrations.cdek.enabled', false)
            ->assertJsonPath('integrations.payments.enabled', false)
            ->assertJsonPath('integrations.payments.provider', null);
    }

    /** @param array<string, string> $env */
    private function cdekEndpointWith(array $env): string
    {
        foreach (['CDEK_TEST', 'CDEK_BASE_URL'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        foreach ($env as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }

        $config = require base_path('config/services.php');

        foreach (array_keys($env) as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        return $config['cdek']['base_url'];
    }
}
