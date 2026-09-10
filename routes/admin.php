<?php

use App\Enums\Guard;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentImageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->name('login');

Route::middleware(Guard::Admin->middleware())->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');

    Route::get('statistics', StatisticsController::class)->name('statistics');

    Route::apiResource('pages', PageController::class)->parameters(['pages' => 'page:id']);
    Route::post('content/images', ContentImageController::class)->name('content.images.store');

    Route::prefix('site')->name('site.')->group(function (): void {
        Route::get('/', [SiteController::class, 'show'])->name('show');
        Route::put('/', [SiteController::class, 'update'])->name('update');
        Route::post('banner', [SiteController::class, 'storeBanner'])->name('banner.store');
        Route::delete('banner', [SiteController::class, 'destroyBanner'])->name('banner.destroy');
    });

    Route::apiResource('categories', CategoryController::class)->parameters(['categories' => 'category:id']);
    Route::put('categories/{category:id}/position', [CategoryController::class, 'move'])->name('categories.move');
    Route::apiResource('products', ProductController::class)->parameters(['products' => 'product:id']);
    Route::apiResource('orders', OrderController::class)->parameters(['orders' => 'order:id']);

    // Scoped bindings resolve the image through its product, so an id from
    // another product's gallery is a 404 rather than a cross-product edit.
    Route::prefix('products/{product:id}/images')
        ->name('products.images.')
        ->scopeBindings()
        ->group(function (): void {
            Route::get('/', [ProductImageController::class, 'index'])->name('index');
            Route::post('/', [ProductImageController::class, 'store'])->name('store');
            Route::put('{image:id}/primary', [ProductImageController::class, 'primary'])->name('primary');
            Route::delete('{image:id}', [ProductImageController::class, 'destroy'])->name('destroy');
        });

    Route::apiResource('customers', CustomerController::class)
        ->parameters(['customers' => 'customer:id'])
        ->only(['index', 'show', 'update']);

    Route::post('customers/{customer:id}/block', [CustomerController::class, 'block'])->name('customers.block');
    Route::delete('customers/{customer:id}/block', [CustomerController::class, 'unblock'])->name('customers.unblock');
});
