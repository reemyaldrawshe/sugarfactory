<?php
// app/Http/Controllers/Tester/ShipmentController.php
namespace App\Http\Controllers\Tester;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tester\Shipment\LabApproveRequest;
use App\Http\Requests\Tester\Shipment\LabResultRequest;
use App\Http\Requests\Tester\Shipment\LabRejectRequest;
use App\Http\Responses\Response;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    /**
     * 7. Lab upload test results
     */
    public function uploadResult(LabResultRequest $request): JsonResponse
    {
        try {
            $item = $this->service->labUploadResult($request->validated(), auth()->user());
            return Response::Success($item, __('shipment.lab_result_uploaded'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * 7b. Lab approve shipment
     */
    public function approve(LabApproveRequest $request, int $id): JsonResponse
    {
        try {
            $shipment = $this->service->labApprove($request, $id, auth()->user());
            return Response::Success($shipment, __('shipment.lab_approved'));
        } catch (Throwable $th) {
            activity('Error: Tester approve')->log($th);
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * 7c. Lab reject shipment
     */
    public function reject(int $id, LabRejectRequest $request): JsonResponse
    {
        try {
            $shipment = $this->service->labReject($id, auth()->user(), $request->reason);
            return Response::Success($shipment, __('shipment.lab_rejected'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * Get pending lab shipments
     */
    public function index(): JsonResponse
    {
        try {
            $shipments = $this->service->getShipmentsByRole('tester', request()->all());
            return Response::Success($shipments, __('shipment.pending_lab'));
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}
