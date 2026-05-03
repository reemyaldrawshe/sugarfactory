<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BOM\StoreRequest;
use App\Http\Requests\Admin\BOM\UpdateRequest;
use App\Http\Responses\Response;
use App\Models\BOM;
use App\Services\Admin\BOMService;
use Illuminate\Http\JsonResponse;
use Throwable;

class BOMController extends Controller
{
    private BOMService $bomService;

    public function __construct(BOMService $bomService)
    {
        $this->bomService = $bomService;
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $data = [];

        try {
            $data = $this->bomService->updateOrCreate($request->validated());

            return Response::Success($data, __('bom.created'));
        } catch (Throwable $th) {
            activity('Error: Admin BOM Store')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }


    public function destroy(BOM $bom): JsonResponse
    {
        $data = [];

        try {
            $this->bomService->delete($bom);

            return Response::Success($data, __('bom.deleted'));
        } catch (Throwable $th) {
            activity('Error: Admin BOM Delete')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }
}
