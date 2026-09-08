<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\ListCategories;
use App\Actions\Catalog\ListProducts;
use App\Actions\Catalog\RecordProductView;
use App\Http\Requests\ListProductsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogController extends Controller
{
    public function categories(ListCategories $listCategories): AnonymousResourceCollection
    {
        return CategoryResource::collection($listCategories());
    }

    public function products(ListProductsRequest $request, ListProducts $listProducts): AnonymousResourceCollection
    {
        return ProductResource::collection($listProducts(
            $request->categorySlug(),
            $request->search(),
            $request->sort(),
            $request->inStockOnly(),
            $request->perPage(),
        ));
    }

    public function product(Product $product, RecordProductView $recordProductView): ProductResource
    {
        if (! $product->status->isVisibleInCatalog()) {
            throw new NotFoundHttpException;
        }

        $recordProductView($product);

        return new ProductResource($product->load(['category', 'images']));
    }
}
