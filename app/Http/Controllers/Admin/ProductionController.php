<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductionStatusEnum;
use App\Models\ProductionOrder;
use Throwable;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Admin\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        private readonly ProductionService $service
    ) {}

    public function index(): JsonResponse {

        $data = [];

        try {

            $data = ProductionOrder::query()->where('status', '=', ProductionStatusEnum::PENDING->value)->get();

            return Response::Success(
                $data,
                'Production order with pending status fetched successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Admin Index Production Order')
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
    | Approve Order
    |--------------------------------------------------------------------------
    */

    public function approve($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service->approve($id);

            return Response::Success(
                $data,
                'Production order approved successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Approve Production Order')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reject Order
    |--------------------------------------------------------------------------
    */

    public function reject($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service->reject($id);

            return Response::Success(
                $data,
                'Production order rejected successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Reject Production Order')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }
}
