<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Unit\StoreRequest;
use App\Http\Requests\Admin\Unit\UpdateRequest;
use App\Http\Responses\Response;
use App\Services\Admin\UnitService;
use Illuminate\Http\JsonResponse;
use Throwable;

class UnitController extends Controller
{
    private UnitService $unitService;

    public function __construct(UnitService $unitService)
    {
        $this->unitService = $unitService;
    }

    public function index(): JsonResponse
    {
        $data = [];
        try {
            $data = $this->unitService->list();
            $message = __('unit.index');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Unit Index')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $data = [];
        try {
            $data = $this->unitService->create($request->validated());
            $message = __('unit.created');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Unit Store')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->unitService->update($id, $request->validated());
            $message = __('unit.updated');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Unit Update')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->unitService->delete($id);
            $message = __('unit.deleted');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Unit Delete')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function show(int $id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->unitService->show($id);
            $message = __('unit.found');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Unit Show')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }
}