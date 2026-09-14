<?php

namespace App\Http\Controllers;

use App\Actions\Cart\ResolveCart;
use App\Actions\Delivery\ListCdekCities;
use App\Actions\Delivery\ListCdekPoints;
use App\Actions\Delivery\QuoteCdekTariffs;
use App\Http\Requests\Delivery\CdekCitiesRequest;
use App\Http\Requests\Delivery\CdekPointsRequest;
use App\Http\Requests\Delivery\CdekTariffsRequest;
use App\Http\Resources\Delivery\CdekCityResource;
use App\Http\Resources\Delivery\CdekPointResource;
use App\Http\Resources\Delivery\CdekTariffResource;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryController extends Controller
{
    public function cities(CdekCitiesRequest $request, ListCdekCities $listCdekCities): AnonymousResourceCollection
    {
        return CdekCityResource::collection($listCdekCities($request->search()));
    }

    public function points(CdekPointsRequest $request, ListCdekPoints $listCdekPoints): AnonymousResourceCollection
    {
        return CdekPointResource::collection($listCdekPoints($request->cityCode()));
    }

    /**
     * Priced for the cart in hand: the weight the carrier is asked about is
     * the weight of what the buyer is actually about to order.
     */
    public function tariffs(
        CdekTariffsRequest $request,
        ResolveCart $resolveCart,
        QuoteCdekTariffs $quoteCdekTariffs,
    ): AnonymousResourceCollection {
        /** @var ?Customer $customer */
        $customer = $request->user();

        return CdekTariffResource::collection($quoteCdekTariffs(
            $resolveCart($customer),
            $request->cityCode(),
            $request->destination(),
            $request->pointCode(),
        ));
    }
}
