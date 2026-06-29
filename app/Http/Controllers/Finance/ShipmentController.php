<?php
namespace App\Http\Controllers\Finance; // 👈 تأكد من تطابق هذا المسار تماماً
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
                $this->service->getShipmentsByRole('finance', request()->all()),
                 'finance view'
                         );
            // return Response::Success(
            //     $this->service->list(['status' => ShipmentStatus::FINISHED]),
            //     'finance view'
            // );
            // في الكنترولر بدلاً من list() استخدم:

        }catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
    public function pay(int $id): JsonResponse
    {
        try {
            // جلب موظف المالية الذي قام بالعملية
            $financeUser = auth()->user(); 
            
            // استدعاء دالة الدفع من الـ Service
            $shipment = $this->service->payShipment($id, $financeUser);

            return Response::Success(
                $shipment,
                'تم دفع الشحنة بنجاح'
            );
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}
