<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Customers\BlockCustomer;
use App\Actions\Admin\Customers\ListCustomers;
use App\Actions\Admin\Customers\UnblockCustomer;
use App\Actions\Admin\Customers\UpdateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListCustomersRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Http\Resources\Admin\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(ListCustomersRequest $request, ListCustomers $listCustomers): AnonymousResourceCollection
    {
        return CustomerResource::collection(
            $listCustomers($request->search(), $request->blocked(), $request->perPage()),
        );
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->loadCount('orders'));
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        UpdateCustomer $updateCustomer,
    ): CustomerResource {
        return new CustomerResource($updateCustomer($customer, $request->customer()));
    }

    public function block(Customer $customer, BlockCustomer $blockCustomer): CustomerResource
    {
        return new CustomerResource($blockCustomer($customer));
    }

    public function unblock(Customer $customer, UnblockCustomer $unblockCustomer): CustomerResource
    {
        return new CustomerResource($unblockCustomer($customer));
    }
}
