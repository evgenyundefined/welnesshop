<?php

namespace App\Http\Controllers;

use App\Actions\Cart\ResolveCart;
use App\Actions\Delivery\PriceDelivery;
use App\Actions\Orders\PlaceOrder;
use App\Delivery\Cdek\CdekUnavailable;
use App\Exceptions\CartIsEmpty;
use App\Exceptions\ProductNotAvailable;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Jobs\AnnounceOrder;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    /**
     * @throws CartIsEmpty
     * @throws CdekUnavailable
     * @throws ProductNotAvailable
     * @throws \Throwable
     */
    public function __invoke(
        CheckoutRequest $request,
        ResolveCart $resolveCart,
        PriceDelivery $priceDelivery,
        PlaceOrder $placeOrder,
    ): JsonResponse {
        /** @var Customer $customer */
        $customer = $request->user();
        $cart = $resolveCart($customer);
        $details = $request->details();

        // Priced here rather than taken from the browser: the cost that
        // reaches the order is the one the carrier quotes at this moment.
        $delivery = $priceDelivery($cart, $details['delivery_method'], $request->deliverySelection());

        $order = $placeOrder($customer, $cart, [...$details, ...$delivery]);

        // Queued, and after the order exists: the buyer waits for none of it,
        // and a mail server that is down cannot roll back committed stock.
        AnnounceOrder::dispatch($order);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
