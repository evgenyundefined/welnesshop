<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;

class StorefrontController extends Controller
{
    public function __construct(private readonly ViewFactory $view) {}

    public function __invoke(): View
    {
        return $this->view->make('storefront');
    }
}
