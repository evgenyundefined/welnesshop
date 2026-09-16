<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionEnvironmentTest extends TestCase
{
    /**
     * A compose service only sees the variables its own environment block
     * names: a value set on the stack reaches nothing by itself. Forgetting an
     * entry there is invisible in development and silently disables a feature
     * in production, which is how CDEK and ЮKassa first shipped switched off.
     */
    public function test_every_variable_the_shop_reads_is_passed_to_the_app_container(): void
    {
        $passed = $this->appServiceEnvironment();

        $missing = array_values(array_diff($this->variablesReadByOurConfig(), $passed));

        $this->assertSame(
            [],
            $missing,
            'add to the app service in docker-compose.prod.yml: '.implode(', ', $missing),
        );
    }

    /**
     * A service whose image is built on the machine has to say so, or the
     * deploy tries to pull it from a registry where it has never existed and
     * stops the whole stack with "pull access denied".
     */
    public function test_every_locally_built_service_declares_its_build(): void
    {
        $built = $this->servicesRunningALocalImage();

        $this->assertNotEmpty($built, 'the production stack no longer names a locally built image');

        preg_match_all('/^  ([a-z0-9-]+):/m', (string) file_get_contents(base_path('docker-compose.build.yml')), $declared);

        $missing = array_values(array_diff($built, $declared[1]));

        $this->assertSame([], $missing, 'add to docker-compose.build.yml: '.implode(', ', $missing));
    }

    /**
     * Service names in docker-compose.prod.yml whose image is one this
     * repository builds rather than one a registry serves.
     *
     * @return list<string>
     */
    private function servicesRunningALocalImage(): array
    {
        $lines = explode("\n", (string) file_get_contents(base_path('docker-compose.prod.yml')));

        $service = null;
        $built = [];

        foreach ($lines as $line) {
            if (preg_match('/^  ([a-z0-9-]+):$/', $line, $name) === 1) {
                $service = $name[1];

                continue;
            }

            if ($service !== null && preg_match('/^    image: .*\$\{(APP|NGINX)_IMAGE/', $line) === 1) {
                $built[] = $service;
            }
        }

        return $built;
    }

    /** @return list<string> */
    private function appServiceEnvironment(): array
    {
        $compose = (string) file_get_contents(base_path('docker-compose.prod.yml'));
        $app = (string) strstr(strstr($compose, "\n  app:") ?: '', "\n  nginx:", true);

        preg_match_all('/^      ([A-Z0-9_]+):/m', $app, $matches);

        return $matches[1];
    }

    /** @return list<string> */
    private function variablesReadByOurConfig(): array
    {
        $names = [];

        foreach (['shop', 'services', 'mail'] as $file) {
            preg_match_all("/env\\('([A-Z0-9_]+)'/", (string) file_get_contents(config_path("{$file}.php")), $matches);
            $names = [...$names, ...$matches[1]];
        }

        // Both files also carry Laravel's stock third-party drivers, which
        // this shop does not use; MAIL_ is kept because order notifications
        // depend on it, minus the per-driver settings we never touch.
        $prefixes = ['SHOP_', 'CDEK_', 'YOOKASSA_', 'TELEGRAM_', 'MAIL_'];
        $ignored = ['MAIL_EHLO_DOMAIN', 'MAIL_SENDMAIL_PATH', 'MAIL_LOG_CHANNEL', 'MAIL_URL', 'MAIL_ENCRYPTION'];

        $ours = array_filter(
            $names,
            static fn (string $name): bool => ! in_array($name, $ignored, true)
                && array_any($prefixes, static fn (string $prefix): bool => str_starts_with($name, $prefix)),
        );

        return array_values(array_unique($ours));
    }
}
