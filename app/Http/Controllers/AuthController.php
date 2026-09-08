<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginCustomer;
use App\Actions\Auth\LogoutCustomer;
use App\Actions\Auth\RegisterCustomer;
use App\Exceptions\InvalidCredentials;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterCustomer $registerCustomer): JsonResponse
    {
        return (new CustomerResource($registerCustomer($request->customer())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @throws InvalidCredentials
     */
    public function login(LoginRequest $request, LoginCustomer $loginCustomer): CustomerResource
    {
        return new CustomerResource(
            $loginCustomer($request->email(), $request->password(), $request->remember()),
        );
    }

    public function logout(LogoutCustomer $logoutCustomer): Response
    {
        $logoutCustomer();

        return response()->noContent();
    }

    public function me(Request $request): CustomerResource
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return new CustomerResource($customer);
    }
}
