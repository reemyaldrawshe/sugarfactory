<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\ProductionStatusEnum;
use App\Models\ProductionOrder;
use Throwable;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Warehouse\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        private readonly ProductionService $service
    ) {}

    public function index(): JsonResponse {

        $data = [];

        try {

            $data = ProductionOrder::query()->where('status', '=', ProductionStatusEnum::APPROVED_BY_MANAGER->value)->get();

            return Response::Success(
                $data,
                'Production order with approved by manager status fetched successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Warehouse Index Production Order')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    public function show($id): JsonResponse {

        $data = [];

        try {

            $data = ProductionOrder::query()->findOrFail($id);

            return Response::Success(
                $data,
                'Production order fetched successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Show Production Order')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Reserve Materials
    |--------------------------------------------------------------------------
    */

    public function reserve($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service
                ->reserveMaterials($id);

            return Response::Success(
                $data,
                'Materials reserved successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Reserve Materials')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Send To Production
    |--------------------------------------------------------------------------
    */

    public function send($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service
                ->sendToProduction($id);

            return Response::Success(
                $data,
                'Order sent to production successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Send To Production')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }
}
