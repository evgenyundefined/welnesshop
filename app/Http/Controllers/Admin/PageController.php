<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Pages\DeletePage;
use App\Actions\Admin\Pages\ListPages;
use App\Actions\Admin\Pages\SavePage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPagesRequest;
use App\Http\Requests\Admin\SavePageRequest;
use App\Http\Resources\Admin\PageResource;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function index(ListPagesRequest $request, ListPages $listPages): AnonymousResourceCollection
    {
        return PageResource::collection($listPages($request->search(), $request->perPage()));
    }

    public function store(SavePageRequest $request, SavePage $savePage): JsonResponse
    {
        return (new PageResource($savePage($request->page())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Page $page): PageResource
    {
        return new PageResource($page);
    }

    public function update(SavePageRequest $request, Page $page, SavePage $savePage): PageResource
    {
        return new PageResource($savePage($request->page(), $page));
    }

    public function destroy(Page $page, DeletePage $deletePage): Response
    {
        $deletePage($page);

        return response()->noContent();
    }
}
