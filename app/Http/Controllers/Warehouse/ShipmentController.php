<?php
// app/Http/Controllers/Warehouse/ShipmentController.php
namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\Shipment\CreatePurchaseRequest;
use App\Http\Responses\Response;
use App\Models\User;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ShipmentController extends Controller
{

    public function __construct(private ShipmentService $service) {}

    /**
     * 1. Create purchase request
     */
    public function store(CreatePurchaseRequest $request): JsonResponse
    {
        try {
            $shipment = $this->service->createPurchaseRequest(
                $request->validated(),
                auth()->user()
            );
            return Response::Success($shipment, __('shipment.created'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * 5. Confirm receipt before lab
     */
    public function confirmReceipt(int $id): JsonResponse
    {
        try {
            $shipment = $this->service->warehouseConfirmReceipt($id, auth()->user());
            return Response::Success($shipment, __('shipment.receipt_confirmed'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * 6. Send to lab
     */
    public function sendToLab(int $id): JsonResponse
    {
        try {
            $shipment = $this->service->sendToLab($id, auth()->user());
            return Response::Success($shipment, __('shipment.sent_to_lab'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * 8. Final confirmation - add to inventory
     */
    public function finalConfirm(int $id)
    {
        try {
            $shipment = $this->service->finalConfirm($id, auth()->user());
            return Response::Success($shipment, __('shipment.final_confirmed'));
        } catch (Throwable $th) {
            return $th;
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * Get warehouse shipments
     */
    public function index(): JsonResponse
    {
        try {
            $shipments = $this->service->getShipmentsByRole('warehouse', request()->all());
            return Response::Success($shipments, __('shipment.list'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * Get shipment details with tracking
     */
    public function show(int $id): JsonResponse
    {
        try {
            $shipment = $this->service->getShipmentWithTracking($id);
            return Response::Success($shipment, __('shipment.details'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}
