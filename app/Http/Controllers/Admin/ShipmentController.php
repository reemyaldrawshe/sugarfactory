<?php
// app/Http/Controllers/Admin/ShipmentController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    /**
     * 2. Admin approve purchase request
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $shipment = $this->service->adminApprove($id, auth()->user());
            return Response::Success($shipment, __('shipment.admin_approved'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * Get pending admin shipments
     */
    public function index(): JsonResponse
    {
        try {
            $shipments = $this->service->getShipmentsByRole('admin', request()->all());
            return Response::Success($shipments, __('shipment.list'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}
