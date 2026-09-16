<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\ListCategories;
use App\Actions\Checkout\AvailableDeliveryMethods;
use App\Actions\Checkout\AvailablePaymentMethods;
use App\Actions\Site\ListFooterProducts;
use App\Actions\Site\ListPages;
use App\Actions\Site\LoadSiteSettings;
use App\Http\Resources\SiteResource;
use App\Payments\PaymentGateway;
use Illuminate\Config\Repository as Config;

class SiteController extends Controller
{
    public function __invoke(
        LoadSiteSettings $loadSiteSettings,
        ListPages $listPages,
        ListCategories $listCategories,
        ListFooterProducts $listFooterProducts,
        PaymentGateway $gateway,
        Config $config,
        AvailableDeliveryMethods $deliveryMethods,
        AvailablePaymentMethods $paymentMethods,
    ): SiteResource {
        return new SiteResource([
            'settings' => $loadSiteSettings(),
            'pages' => $listPages(),
            'categories' => $listCategories(),
            'products' => $listFooterProducts(),
            'online_payment' => $gateway->isLive(),
            'max_item_quantity' => $config->integer('shop.max_item_quantity'),
            'delivery_methods' => $deliveryMethods(),
            'payment_methods' => $paymentMethods(),
        ]);
    }
}
