<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Orders\CreateOrder;
use App\Actions\Admin\Orders\DeleteOrder;
use App\Actions\Admin\Orders\ListOrders;
use App\Actions\Admin\Orders\ShowOrder;
use App\Actions\Admin\Orders\UpdateOrder;
use App\Exceptions\CancelledOrderIsFinal;
use App\Exceptions\ProductNotAvailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateOrderRequest;
use App\Http\Requests\Admin\ListOrdersRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request, ListOrders $listOrders): AnonymousResourceCollection
    {
        return OrderResource::collection($listOrders($request->search(), $request->status(), $request->perPage()));
    }

    /**
     * @throws ProductNotAvailable
     * @throws \Throwable
     */
    public function store(CreateOrderRequest $request, CreateOrder $createOrder): JsonResponse
    {
        $customer = Customer::query()->findOrFail($request->integer('customer_id'));

        return (new OrderResource($createOrder($customer, $request->quantities(), $request->details())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Order $order, ShowOrder $showOrder): OrderResource
    {
        return new OrderResource($showOrder($order));
    }

    /**
     * @throws CancelledOrderIsFinal
     * @throws \Throwable
     */
    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $updateOrder): OrderResource
    {
        return new OrderResource($updateOrder($order, $request->order()));
    }

    /**
     * @throws \Throwable
     */
    public function destroy(Order $order, DeleteOrder $deleteOrder): Response
    {
        $deleteOrder($order);

        return response()->noContent();
    }
}
