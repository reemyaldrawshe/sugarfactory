<?php

namespace App\Http\Controllers\Warehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\Item\IndexRequest;
use App\Http\Requests\Warehouse\Item\StoreRequest;
use App\Http\Requests\Warehouse\Item\UpdateRequest;
use App\Http\Requests\Warehouse\SectionsRequest;
use App\Http\Requests\Warehouse\UnitsRequest;
use App\Http\Responses\Response;
use App\Models\Item;
use App\Services\Warehouse\ItemService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ItemController extends Controller
{
    private ItemService $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    public function index(): JsonResponse
    {
        try {

            $items = $this->itemService->index();

            return Response::Success(
                $items,
                __('item.index')
            );

        } catch (Throwable $th) {

            activity('Error: Warehouse Item Index')
                ->log($th->getMessage());

            return Response::Error(
                [],
                $th->getMessage()
            );
        }
    }

    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $item = $this->itemService->store($request->validated());

            return Response::Success(
                $item,
                __('item.created')
            );
        } catch (Throwable $th) {
            activity('Error: Admin item Store')->log($th);
            return Response::Error(
                [],
                $th->getMessage()
            );
        }
    }

    public function show(Item $item): JsonResponse
{
    try {

        $item = $this->itemService->show($item);

        return Response::Success(
            $item,
            __('item.found')
        );

    } catch (Throwable $th) {

        activity('Error: Warehouse Item Show')
            ->log($th->getMessage());

        return Response::Error(
            [],
            $th->getMessage()
        );
    }
}

    public function update(UpdateRequest $request, item $item): JsonResponse
    {
        try {
            $item = $this->itemService->update($item, $request->validated());

            return Response::Success(
                $item,
                __('item.updated')
            );
        } catch (Throwable $th) {
            activity('Error: Admin item Update')->log($th);
            return Response::Error(
                [],
                $th->getMessage()
            );
        }
    }

    public function destroy(item $item): JsonResponse
    {
        try {
            return Response::Success(
                $this->itemService->delete($item),
                __('item.deleted')
            );
        } catch (Throwable $th) {
            activity('Error: Admin item Destroy')->log($th);
            return Response::Error(
                [],
                $th->getMessage()
            );
        }
    }


public function sections(
    SectionsRequest $request
): JsonResponse
{
    try {

        return Response::Success(

            $this->itemService->sections(),

            'تم جلب الأقسام بنجاح'
        );

    } catch (Throwable $th) {

        return Response::Error(
            [],
            $th->getMessage()
        );
    }
}


public function units(
    UnitsRequest $request
): JsonResponse
{
    try {

        return Response::Success(

            $this->itemService->units(),

            'تم جلب الوحدات بنجاح'
        );

    } catch (Throwable $th) {

        return Response::Error(
            [],
            $th->getMessage()
        );
    }
}

}
