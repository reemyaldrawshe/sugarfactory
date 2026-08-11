<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Warehouse\InventoryAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InventoryAuditController extends Controller
{
    // public function __construct(private readonly InventoryAuditService $inventoryAuditService) {}
public function __construct(private readonly InventoryAuditService $auditService) {}

    /**
     * جلب قائمة طلبات الجرد
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'code', 'date_from', 'date_to']);
            $perPage = $request->get('per_page', 15);

            $data = $this->auditService->getAuditOrders($filters, $perPage);

            return Response::Success($data, 'تم جلب طلبات الجرد بنجاح');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * إنشاء طلب جرد جديد (سلسلة دفعات)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string',
            'items' => 'required|array|min:1',
           // 'items.*.section_id' => 'required|exists:sections,id',
            'items.*.shipment_item_id' => 'required|exists:shipment_items,id',
            'items.*.actual_quantity' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $data = $this->auditService->createAuditOrder($request->all());

            return Response::Success($data, 'تم تقديم طلب الجرد بنجاح وهو قيد الانتظار', 201);
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * موافقة الأدمن على طلب الجرد
     */
    public function approve($id): JsonResponse
    {
        try {
            $data = $this->auditService->approveAuditOrder($id);

            return Response::Success($data, 'تمت الموافقة على طلب الجرد وتحديث المخزون');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    /**
     * رفض طلب الجرد من قبل الأدمن
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        try {
            $data = $this->auditService->rejectAuditOrder($id, $request->rejection_reason);

            return Response::Success($data, 'تم رفض طلب الجرد');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
    /**
     * جلب هيكل البيانات الخاص ببدء عملية الجرد
     */
    public function getAuditData(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['section_id', 'item_id', 'search']);

            $data = $this->auditService->getAuditData($filters);

            return Response::Success($data, __('inventory.audit_data_retrieved_successfully'));
        } catch (Throwable $th) {
            activity('Error: Warehouse Inventory Audit Data')->log($th);
            return Response::Error([], $th->getMessage());
        }
    }
    /**
 * جلب تفاصيل طلب جرد محدد
 */
public function show($id): JsonResponse
{
    try {
        $data = $this->auditService->getAuditOrderById($id);

        return Response::Success($data, 'تم جلب تفاصيل طلب الجرد بنجاح');
    } catch (Throwable $th) {
        return Response::Error([], $th->getMessage());
    }
}
}