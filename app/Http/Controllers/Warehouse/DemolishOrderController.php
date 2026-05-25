<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\DemolishOrder\CreateDemolishOrderRequest;
use App\Http\Requests\Warehouse\DemolishOrder\UpdateDemolishOrderRequest;
use App\Http\Responses\Response;
use App\Services\Warehouse\DemolishOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class DemolishOrderController extends Controller
{
    public function __construct(private readonly DemolishOrderService $demolishOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $data = [];
        try {
            $filters = $request->only(['status', 'section_id', 'item_id', 'date_from', 'date_to']);
            $perPage = $request->get('per_page', 50);

            $data = $this->demolishOrderService->getDemolishOrders($filters, $perPage);

            return Response::Success($data, __('demolish.orders_retrieved_successfully'));
        } catch (Throwable $th) {
            activity('Error: Admin Demolish Orders Index')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function store(CreateDemolishOrderRequest $request): JsonResponse
    {
        $data = [];
        try {
            $data = $this->demolishOrderService->create($request->validated());

            return Response::Success($data, __('demolish.order_created_successfully'), 201);
        } catch (Throwable $th) {
            activity('Error: Admin Demolish Order Store')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function show($id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->demolishOrderService->getDemolishOrder($id);

            if (!$data) {
                return Response::Error($data, __('demolish.order_not_found'), 404);
            }

            return Response::Success($data, __('demolish.order_retrieved_successfully'));
        } catch (Throwable $th) {
            activity('Error: Admin Demolish Order Show')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function update(UpdateDemolishOrderRequest $request, $id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->demolishOrderService->update($id, $request->validated());

            return Response::Success($data, __('demolish.order_updated_successfully'));
        } catch (Throwable $th) {
            activity('Error: Admin Demolish Order Update')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function destroy($id): JsonResponse
    {
        $data = [];
        try {
            $this->demolishOrderService->delete($id);
            $data = ['deleted_id' => $id];

            return Response::Success($data, __('demolish.order_deleted_successfully'));
        } catch (Throwable $th) {
            activity('Error: Admin Demolish Order Destroy')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function getStatistics(): JsonResponse
    {
        $data = [];
        try {
            $data = $this->demolishOrderService->getDemolishStatistics();

            return Response::Success($data, __('demolish.statistics_retrieved_successfully'));
        } catch (Throwable $th) {
            activity('Error: Admin Demolish Statistics')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }
}
