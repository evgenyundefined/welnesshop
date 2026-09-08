<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/{path?}', AdminPanelController::class)
    ->where('path', '^(?!api(/|$)).*$')
    ->name('admin');

Route::get('/{path?}', StorefrontController::class)
    ->where('path', '^(?!(api|admin)(/|$)).*$')
    ->name('storefront');
