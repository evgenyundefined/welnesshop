<?php

namespace App\Providers;

use App\Actions\Admin\Auth\LoginAdmin;
use App\Actions\Admin\Auth\LogoutAdmin;
use App\Enums\Guard;
use App\Payments\PaymentGateway;
use App\Payments\PendingPaymentGateway;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap this binding for a real acquirer to turn payments on.
        $this->app->bind(PaymentGateway::class, PendingPaymentGateway::class);

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

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();
        URL::forceHttps($this->app->isProduction());
    }
}
