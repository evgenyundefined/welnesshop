<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Products\DeleteProduct;
use App\Actions\Admin\Products\ListProducts;
use App\Actions\Admin\Products\SaveProduct;
use App\Exceptions\ProductIsOrdered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListProductsRequest;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Product;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request, ListProducts $listProducts): AnonymousResourceCollection
    {
        return ProductResource::collection($listProducts(
            $request->search(),
            $request->categoryId(),
            $request->status(),
            $request->perPage(),
        ));
    }

    public function store(SaveProductRequest $request, Config $config, SaveProduct $saveProduct): JsonResponse
    {
        return (new ProductResource($saveProduct($request->product($config))))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'images', 'primaryImage']));
    }

    public function update(
        SaveProductRequest $request,
        Product $product,
        Config $config,
        SaveProduct $saveProduct,
    ): ProductResource {
        return new ProductResource($saveProduct($request->product($config), $product));
    }

    /**
     * @throws ProductIsOrdered
     */
    public function destroy(Product $product, DeleteProduct $deleteProduct): Response
    {
        $deleteProduct($product);

        return response()->noContent();
    }
}
