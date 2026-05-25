<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\ItemTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ItemTrackingLogController extends Controller
{
    public function __construct(private readonly ItemTrackingService $itemTrackingService) {}

    public function index(Request $request): JsonResponse
    {
        $data = [];
        try {
            $filters = $request->only(['type', 'item_id', 'shipment_id', 'date_from', 'date_to']);
            $perPage = $request->get('per_page', 50);

            $data = $this->itemTrackingService->getTrackingLogs($filters, $perPage);

            return Response::Success($data, __('tracking.logs_retrieved_successfully'));
        } catch (Throwable $th) {
            activity('Error: Admin Item Tracking Logs')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

}
