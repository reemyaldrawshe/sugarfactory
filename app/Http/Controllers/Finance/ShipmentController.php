<?php
namespace App\Http\Controllers\Finance;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    public function index(): JsonResponse
    {
        try {
            return Response::Success(
                $this->service->list(['status' => ShipmentStatus::FINISHED]),
                'finance view'
            );
        }catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}
