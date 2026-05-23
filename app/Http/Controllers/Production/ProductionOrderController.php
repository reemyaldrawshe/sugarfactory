<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Order\IndexRequest;
use App\Http\Requests\Production\Order\StoreRequest;
use App\Http\Responses\Response;
use App\Models\ProductionOrder;
use App\Services\Production\ProductionOrderService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $service
    ) {}

    public function store(
        StoreRequest $request
    ): JsonResponse {

        $data = [];

        try {

            $data = $this->service->create(
                $request->validated()
            );

            return Response::Success(
                $data,
                'Production order created successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Create Production Order')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    public function index(IndexRequest $request): JsonResponse {

        $data = [];

        try {

            $query = ProductionOrder::query();
            if(isset($request['status'])){
                $query->where('status', $request['status']);
            }
            $data = $query->get();

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
}
