<?php

namespace App\Providers;

use App\Actions\Admin\Auth\LoginAdmin;
use App\Actions\Admin\Auth\LogoutAdmin;
use App\Enums\Guard;
use App\Payments\PaymentGateway;
use App\Payments\PendingPaymentGateway;
use App\Payments\YooKassaGateway;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ЮKassa takes over as soon as its credentials are configured and the
        // shop has online payment switched on; otherwise the shop keeps taking
        // orders and says payment is not available. The switch is separate from
        // the credentials so turning it off costs nothing to undo.
        $this->app->bind(PaymentGateway::class, static function (Application $app): PaymentGateway {
            $config = $app->make(Config::class);

            return $config->boolean('shop.integrations.online_payment') && YooKassaGateway::isConfigured($config)
                ? $app->make(YooKassaGateway::class)
                : $app->make(PendingPaymentGateway::class);
        });

        // Both guards are named rather than taken from the default, which the
        // auth middleware rewrites for the rest of the request as soon as it
        // authenticates somebody.
        $this->app->bind(
            StatefulGuard::class,
            static fn (Application $app): StatefulGuard => $app->make(AuthFactory::class)->guard(Guard::Customer->value),
        );

        $this->app->when([LoginAdmin::class, LogoutAdmin::class])
            ->needs(StatefulGuard::class)
            ->give(static fn (Application $app): StatefulGuard => $app->make(AuthFactory::class)->guard(Guard::Admin->value));
    }

    public function boot(Config $config): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();

        // One definition of "a good enough password", used by registration and
        // by an administrator changing somebody's password.
        Password::defaults(static fn (): Password => Password::min($config->integer('shop.password_min_length')));

        // Generated links follow the scheme the site is actually served on,
        // which APP_URL declares. Forcing https on a deployment still running
        // on plain http points every asset at a port nobody listens on.
        URL::forceHttps(str_starts_with($config->string('app.url'), 'https://'));
    }
}
