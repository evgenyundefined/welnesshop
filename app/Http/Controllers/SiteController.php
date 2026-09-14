<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\ListCategories;
use App\Actions\Site\ListFooterProducts;
use App\Actions\Site\ListPages;
use App\Actions\Site\LoadSiteSettings;
use App\Http\Resources\SiteResource;
use App\Payments\PaymentGateway;

class SiteController extends Controller
{
    public function __invoke(
        LoadSiteSettings $loadSiteSettings,
        ListPages $listPages,
        ListCategories $listCategories,
        ListFooterProducts $listFooterProducts,
        PaymentGateway $gateway,
    ): SiteResource {
        return new SiteResource([
            'settings' => $loadSiteSettings(),
            'pages' => $listPages(),
            'categories' => $listCategories(),
            'products' => $listFooterProducts(),
            'online_payment' => $gateway->isLive(),
        ]);
    }
}
