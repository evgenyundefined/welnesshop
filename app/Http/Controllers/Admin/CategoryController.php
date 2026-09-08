<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Categories\DeleteCategory;
use App\Actions\Admin\Categories\ListCategories;
use App\Actions\Admin\Categories\MoveCategory;
use App\Actions\Admin\Categories\SaveCategory;
use App\Exceptions\CategoryHasProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListCategoriesRequest;
use App\Http\Requests\Admin\MoveCategoryRequest;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function index(ListCategoriesRequest $request, ListCategories $listCategories): AnonymousResourceCollection
    {
        return CategoryResource::collection($listCategories($request->search(), $request->perPage()));
    }

    public function store(SaveCategoryRequest $request, SaveCategory $saveCategory): JsonResponse
    {
        return (new CategoryResource($saveCategory($request->category())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('products'));
    }

    public function update(SaveCategoryRequest $request, Category $category, SaveCategory $saveCategory): CategoryResource
    {
        return new CategoryResource($saveCategory($request->category(), $category));
    }

    /**
     * @throws \Throwable
     */
    public function move(
        MoveCategoryRequest $request,
        Category $category,
        MoveCategory $moveCategory,
    ): Response {
        $moveCategory($category, $request->direction());

        return response()->noContent();
    }

    /**
     * @throws CategoryHasProducts
     */
    public function destroy(Category $category, DeleteCategory $deleteCategory): Response
    {
        $deleteCategory($category);

        return response()->noContent();
    }
}
