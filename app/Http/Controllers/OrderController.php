<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ListOrders;
use App\Actions\Orders\RequestPayment;
use App\Actions\Orders\ShowOrder;
use App\Exceptions\OrderNotPayable;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentIntentResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request, ListOrders $listOrders): AnonymousResourceCollection
    {
        return OrderResource::collection($listOrders($this->customer($request), $request->perPage()));
    }

    public function show(Request $request, Order $order, ShowOrder $showOrder): OrderResource
    {
        return new OrderResource($showOrder($this->customer($request), $order));
    }

    /**
     * @throws OrderNotPayable
     */
    public function pay(
        Request $request,
        Order $order,
        ShowOrder $showOrder,
        RequestPayment $requestPayment,
    ): PaymentIntentResource {
        return new PaymentIntentResource($requestPayment($showOrder($this->customer($request), $order)));
    }

    private function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer;
    }
}
