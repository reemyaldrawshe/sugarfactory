<?php
// app/Http/Controllers/Sales/ShipmentController.php
namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Shipment\UpdatePurchaseRequest;
use App\Http\Responses\Response;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    /**
     * 4. Sales update purchase request with prices and invoice
     */
    public function update(UpdatePurchaseRequest $request): JsonResponse
    {
        try {
            $shipment = $this->service->updatePurchaseRequest(
                $request->validated(),
                auth()->user()
            );
            return Response::Success($shipment, __('shipment.purchase_updated'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * Get pending purchase shipments for sales
     */
    public function index(): JsonResponse
    {
        try {
            $shipments = $this->service->getPendingPurchaseRequests(request()->all());
            return Response::Success($shipments, __('shipment.pending_purchase'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}
