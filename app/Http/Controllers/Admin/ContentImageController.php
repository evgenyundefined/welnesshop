<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Content\StoreContentImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadContentImageRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ContentImageController extends Controller
{
    public function __invoke(UploadContentImageRequest $request, StoreContentImage $storeContentImage): JsonResponse
    {
        return new JsonResponse(
            ['data' => ['url' => $storeContentImage($request->uploadedImage())]],
            Response::HTTP_CREATED,
        );
    }
}
