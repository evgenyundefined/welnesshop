<?php

namespace App\Http\Controllers;

use App\Actions\Cart\AddCartItem;
use App\Actions\Cart\ClearCart;
use App\Actions\Cart\RemoveCartItem;
use App\Actions\Cart\ResolveCart;
use App\Actions\Cart\UpdateCartItemQuantity;
use App\Enums\Guard;
use App\Exceptions\CartItemNotFound;
use App\Exceptions\ProductNotAvailable;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly ResolveCart $resolveCart,
    ) {}

    public function show(Request $request): CartResource
    {
        return $this->present($this->cart($request));
    }

    /**
     * @throws ProductNotAvailable
     */
    public function store(AddCartItemRequest $request, AddCartItem $addCartItem): CartResource
    {
        $cart = $this->cart($request);
        $product = Product::query()->findOrFail($request->productId());

        $addCartItem($cart, $product, $request->quantity());

        return $this->present($cart);
    }

    /**
     * @throws CartItemNotFound
     * @throws ProductNotAvailable
     */
    public function update(
        UpdateCartItemRequest $request,
        CartItem $cartItem,
        UpdateCartItemQuantity $updateQuantity,
    ): CartResource {
        $cart = $this->cart($request);

        $updateQuantity($cart, $cartItem, $request->quantity());

        return $this->present($cart);
    }

    /**
     * @throws CartItemNotFound
     */
    public function destroy(Request $request, CartItem $cartItem, RemoveCartItem $removeCartItem): CartResource
    {
        $cart = $this->cart($request);

        $removeCartItem($cart, $cartItem);

        return $this->present($cart);
    }

    public function clear(Request $request, ClearCart $clearCart): CartResource
    {
        $cart = $this->cart($request);

        $clearCart($cart);

        return $this->present($cart);
    }

    /**
     * The cart routes are open to guests, so the customer is looked up on their
     * own guard rather than on whichever one the request happens to default to.
     */
    private function cart(Request $request): Cart
    {
        $customer = $this->auth->guard(Guard::Customer->value)->user();

        return ($this->resolveCart)($customer instanceof Customer ? $customer : null);
    }

    private function present(Cart $cart): CartResource
    {
        return new CartResource($cart->load('items.product'));
    }
}
