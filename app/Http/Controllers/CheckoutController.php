<?php

namespace App\Http\Controllers;

use App\Actions\Cart\ResolveCart;
use App\Actions\Orders\PlaceOrder;
use App\Exceptions\CartIsEmpty;
use App\Exceptions\ProductNotAvailable;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    /**
     * @throws CartIsEmpty
     * @throws ProductNotAvailable
     * @throws \Throwable
     */
    public function __invoke(CheckoutRequest $request, ResolveCart $resolveCart, PlaceOrder $placeOrder): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $cart = $resolveCart($customer);

        return (new OrderResource($placeOrder($customer, $cart, $request->details())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
