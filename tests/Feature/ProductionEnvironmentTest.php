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

        foreach (['shop', 'services'] as $file) {
            preg_match_all("/env\\('([A-Z0-9_]+)'/", (string) file_get_contents(config_path("{$file}.php")), $matches);
            $names = [...$names, ...$matches[1]];
        }

        // services.php also carries Laravel's stock mail drivers, which this
        // shop does not use.
        $ours = array_filter($names, static fn (string $name): bool => str_starts_with($name, 'SHOP_')
            || str_starts_with($name, 'CDEK_')
            || str_starts_with($name, 'YOOKASSA_'));

        return array_values(array_unique($ours));
    }
}
