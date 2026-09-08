<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Images\AddProductImages;
use App\Actions\Admin\Images\DeleteProductImage;
use App\Actions\Admin\Images\SetPrimaryProductImage;
use App\Exceptions\TooManyProductImages;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadProductImagesRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        return ProductImageResource::collection($product->images);
    }

    /**
     * @throws TooManyProductImages
     * @throws \Throwable
     */
    public function store(
        UploadProductImagesRequest $request,
        Product $product,
        AddProductImages $addProductImages,
    ): JsonResponse {
        $addProductImages($product, $request->images());

        return ProductImageResource::collection($product->images()->get())
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @throws \Throwable
     */
    public function primary(
        Product $product,
        ProductImage $image,
        SetPrimaryProductImage $setPrimaryProductImage,
    ): AnonymousResourceCollection {
        $setPrimaryProductImage($product, $image);

        return ProductImageResource::collection($product->images()->get());
    }

    /**
     * @throws \Throwable
     */
    public function destroy(
        Product $product,
        ProductImage $image,
        DeleteProductImage $deleteProductImage,
    ): AnonymousResourceCollection {
        $deleteProductImage($product, $image);

        return ProductImageResource::collection($product->images()->get());
    }
}
