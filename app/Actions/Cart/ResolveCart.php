<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Config\Repository as Config;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Str;

class ResolveCart
{
    public function __construct(
        private readonly Session $session,
        private readonly Config $config,
    ) {}

    public function __invoke(?Customer $customer): Cart
    {
        if ($customer !== null) {
            return Cart::query()->firstOrCreate(['customer_id' => $customer->id]);
        }

        $sessionKey = $this->config->string('shop.cart_session_key');
        $token = $this->session->get($sessionKey);

        $cart = is_string($token)
            ? Cart::query()->where('token', $token)->first()
            : null;

        if ($cart === null) {
            $cart = Cart::query()->create(['token' => (string) Str::uuid()]);
            $this->session->put($sessionKey, $cart->token);
        }

        return $cart;
    }
}
