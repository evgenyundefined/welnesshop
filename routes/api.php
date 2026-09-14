<?php

use App\Enums\Guard;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('site', SiteController::class)->name('site');
Route::get('pages/{page}', PageController::class)->name('pages.show');

Route::get('categories', [CatalogController::class, 'categories'])->name('categories');
Route::get('products', [CatalogController::class, 'products'])->name('products');
Route::get('products/{product}', [CatalogController::class, 'product'])->name('products.show');

Route::post('register', [AuthController::class, 'register'])->name('register');
Route::post('login', [AuthController::class, 'login'])->name('login');

Route::prefix('cart')->name('cart.')->group(function (): void {
    Route::get('/', [CartController::class, 'show'])->name('show');
    Route::delete('/', [CartController::class, 'clear'])->name('clear');
    Route::post('items', [CartController::class, 'store'])->name('items.store');
    Route::patch('items/{cartItem}', [CartController::class, 'update'])->name('items.update');
    Route::delete('items/{cartItem}', [CartController::class, 'destroy'])->name('items.destroy');
});

// Every call here costs a request to the carrier, so the rate limit is the
// shop's protection as much as CDEK's.
Route::prefix('delivery/cdek')->name('delivery.cdek.')->middleware('throttle:60,1')->group(function (): void {
    Route::get('cities', [DeliveryController::class, 'cities'])->name('cities');
    Route::get('points', [DeliveryController::class, 'points'])->name('points');
    Route::post('tariffs', [DeliveryController::class, 'tariffs'])->name('tariffs');
});

Route::middleware(Guard::Customer->middleware())->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('checkout', CheckoutController::class)->name('checkout');

    Route::prefix('orders')->name('orders.')->group(function (): void {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('{order}', [OrderController::class, 'show'])->name('show');
        Route::post('{order}/pay', [OrderController::class, 'pay'])->name('pay');
    });
});
