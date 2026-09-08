<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Auth\LoginAdmin;
use App\Actions\Admin\Auth\LogoutAdmin;
use App\Exceptions\InvalidCredentials;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\Admin\AdminResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * @throws InvalidCredentials
     */
    public function login(LoginRequest $request, LoginAdmin $loginAdmin): AdminResource
    {
        return new AdminResource($loginAdmin($request->email(), $request->password(), $request->remember()));
    }

    public function logout(LogoutAdmin $logoutAdmin): Response
    {
        $logoutAdmin();

        return response()->noContent();
    }

    public function me(Request $request): AdminResource
    {
        /** @var Admin $admin */
        $admin = $request->user();

        return new AdminResource($admin);
    }
}
