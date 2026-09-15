<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::get('/admin/{path?}', AdminPanelController::class)
    ->where('path', '^(?!api(/|$)).*$')
    ->name('admin');

Route::get('/{path?}', StorefrontController::class)
    ->where('path', '^(?!(api|admin)(/|$)).*$')
    ->name('storefront');
