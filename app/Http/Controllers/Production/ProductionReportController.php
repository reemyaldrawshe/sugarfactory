<?php

namespace App\Http\Controllers\Production;

use Throwable;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Production\ProductionReportService;

class ProductionReportController extends Controller
{
    public function __construct(
        private readonly ProductionReportService $service
    ) {}

    /*
    |--------------------------------------------------------------------------
    | All Orders
    |--------------------------------------------------------------------------
    */

    public function allOrders(): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service->allOrders();

            return Response::Success(
                $data,
                'Production orders fetched successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Fetch Production Orders')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Material Requests
    |--------------------------------------------------------------------------
    */

    public function materialRequests(): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service->materialRequests();

            return Response::Success(
                $data,
                'Material requests fetched successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Fetch Material Requests')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }
}
