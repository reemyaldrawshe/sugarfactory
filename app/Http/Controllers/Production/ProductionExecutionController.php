<?php

namespace App\Http\Controllers\Production;

use App\Http\Requests\Production\CompleteProductionOrderRequest;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Production\ProductionExecutionService;

class ProductionExecutionController extends Controller
{
    public function __construct(
        private readonly ProductionExecutionService $service
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Start Production
    |--------------------------------------------------------------------------
    */

    public function start($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service->start($id);

            return Response::Success(
                $data,
                'Production started successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Start Production')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pause Production
    |--------------------------------------------------------------------------
    */

    public function pause($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service->pause($id);

            return Response::Success(
                $data,
                'Production paused successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Pause Production')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Resume Production
    |--------------------------------------------------------------------------
    */

    public function resume($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service->resume($id);

            return Response::Success(
                $data,
                'Production resumed successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Resume Production')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Production
    |--------------------------------------------------------------------------
    */

    public function complete(
        CompleteProductionOrderRequest $request,
                $id
    ): JsonResponse {

        $data = [];

        try {

            $data = $this->service->complete(
                $id,
                $request->produced_quantity
            );

            return Response::Success(
                $data,
                'Production completed successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Complete Production')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }
}
