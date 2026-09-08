<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use Illuminate\Config\Repository as Config;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Facades\DB;

class MergeGuestCart
{
    public function __construct(
        private readonly Session $session,
        private readonly Config $config,
    ) {}

    public function __invoke(Customer $customer): Cart
    {
        $token = $this->session->pull($this->config->string('shop.cart_session_key'));
        $customerCart = Cart::query()->firstOrCreate(['customer_id' => $customer->id]);

        $guestCart = is_string($token)
            ? Cart::query()->with('items')->whereNull('customer_id')->where('token', $token)->first()
            : null;

        if ($guestCart === null) {
            return $customerCart;
        }

        return DB::transaction(function () use ($guestCart, $customerCart): Cart {
            $maxQuantity = $this->config->integer('shop.max_item_quantity');

            $guestCart->items->each(function (CartItem $item) use ($customerCart, $maxQuantity): void {
                $line = $customerCart->items()->firstOrNew(['product_id' => $item->product_id]);
                $line->quantity = min($line->quantity + $item->quantity, $maxQuantity);
                $line->save();
            });

            $guestCart->delete();

            return $customerCart->load('items.product');
        });
    }
}
