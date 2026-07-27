<?php

namespace App\Http\Controllers\Distribution;

use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Models\DistributionOrder;

use App\Services\Distribution\DistributionOrderService;
use App\Services\Distribution\DistributionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Enums\ProductionStatusEnum;

use Throwable;

class DistributionOrderController extends Controller
{

    public function __construct(
        private readonly DistributionOrderService $orderService,
        private readonly DistributionWorkflowService $workflowService
    ) {}
/**
     * جلب جميع طلبات التوزيع/المبيعات مع تفاصيل المواد والدفعات المخصصة
     */
    public function index(): JsonResponse 
    {
        $data = [];

        try {
            // 1. جلب الطلبات مع كافة العلاقات اللازمة (لتجنب N+1 Query)
            $orders = DistributionOrder::query()
                ->with([
                    'user',                             // الموظف المنشئ
                    'items.item',                       // المواد المطلوبة واسمائها
                    'batchAllocations.shipmentItem',    // تفاصيل الدفعات المخصصة (الصلاحية وغيرها)
                    'batchAllocations.item'             // المادة المرتبطة بالدفعة المخصصة
                ])
                ->latest()
                ->get();

            // 2. تحوير البيانات وتشكيلها لتناسب جميع الأقسام (مستودع، مالية، إدارة)
            $data = $orders->map(function ($order) {
                
                // تحديد ما إذا كان الطلب قد وصل لمرحلة حجز المواد أو ما بعدها
                $isAllocatedOrBeyond = in_array($order->status, [
                    ProductionStatusEnum::DIST_MATERIALS_RESERVED->value,
                    ProductionStatusEnum::DIST_DISPATCHED->value,
                    ProductionStatusEnum::DIST_SOLD->value,
                ]);

                // تشكيل تفاصيل الطلب (المواد والكميات والأسعار)
                $order->order_details = $order->items->map(function ($orderItem) use ($order, $isAllocatedOrBeyond) {
                    
                    // المعلومات الأساسية التي تهم الإدارة والمالية
                    $detail = [
                        'item_id'            => $orderItem->item_id,
                        'item_name'          => $orderItem->item->name ?? 'مادة غير معروفة',
                        'requested_quantity' => $orderItem->quantity,
                        'price_per_ton'      => $orderItem->price_per_ton,
                        'total_price'        => $orderItem->total_price,
                        'is_allocated'       => $isAllocatedOrBeyond,
                        'batches_to_pull'    => [] // سيتم ملؤها للمستودع إذا تم الحجز
                    ];

                    // إذا تم حجز المواد، نقوم بجلب تفاصيل الدفعات (لأمين المستودع)
                    if ($isAllocatedOrBeyond) {
                        // فلترة الحجوزات الخاصة بهذا السطر/المادة تحديداً
                        $allocations = $order->batchAllocations->where('distribution_order_item_id', $orderItem->id);

                        $detail['batches_to_pull'] = $allocations->map(function ($allocation) {
                            return [
                                'shipment_item_id'   => $allocation->shipment_item_id,
                                'expiry_date'        => $allocation->shipmentItem->expiry_date ?? 'بدون تاريخ',
                                'allocated_quantity' => $allocation->allocated_quantity,
                            ];
                        })->values(); // values() لإعادة ترتيب مفاتيح المصفوفة برمجياً
                    }

                    return $detail;
                });

                // 3. حساب الإجمالي الكلي للفاتورة (يهم قسم المالية)
                $order->grand_total_price = $order->items->sum('total_price');

                // 4. تنسيق التواريخ
                $order->created_at_formatted = $order->created_at?->format('Y-m-d H:i:s');
                $order->updated_at_formatted = $order->updated_at?->format('Y-m-d H:i:s');
                $order->approved_at_formatted = $order->approved_at ? \Carbon\Carbon::parse($order->approved_at)->format('Y-m-d H:i:s') : null;
                $order->dispatched_at_formatted = $order->dispatched_at ? \Carbon\Carbon::parse($order->dispatched_at)->format('Y-m-d H:i:s') : null;
                $order->sold_at_formatted = $order->sold_at ? \Carbon\Carbon::parse($order->sold_at)->format('Y-m-d H:i:s') : null;

                // 5. تنظيف الاستجابة من العلاقات الخام (لتقليل حجم الـ JSON وجعله نظيفاً)
                unset($order->items);
                unset($order->batchAllocations);

                return $order;
            });

            return Response::Success(
                $data,
                'تم جلب طلبات التوزيع مع تفاصيل المواد والدفعات بنجاح'
            );

        } catch (\Throwable $th) {
            
            // تسجيل الخطأ في الـ Activity Log كما هو معتمد لديك
            activity('Error: Distribution Index Order')->log($th->getMessage());

            return Response::Error(
                [],
                $th->getMessage()
            );
        }
    }
    // إنشاء طلب جديد
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'customer_name' => 'required|string',
                'notes'         => 'nullable|string',
                'items'         => 'required|array',
                'items.*.item_id' => 'required|exists:items,id',
                'items.*.quantity' => 'required|numeric|min:0.001',
            ]);

            $order = $this->orderService->create($data);
            return Response::Success($order, 'تم إنشاء طلب التوزيع بنجاح');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    // موافقة الإدارة
    public function approve($id): JsonResponse
    {
        try {
            $order = $this->workflowService->approveByManager($id);
            return Response::Success($order, 'تمت الموافقة على الطلب');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    // حجز المواد (FEFO)
    public function reserve($id): JsonResponse
    {
        try {
            $order = $this->workflowService->reserveMaterials($id);
            return Response::Success($order, 'تم حجز المواد من المستودع بنجاح');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    // إرسال للتوصيل (Dispatch)
    public function dispatch($id): JsonResponse
    {
        try {
            $order = $this->workflowService->dispatchOrder($id);
            return Response::Success($order, 'تم إرسال الطلب للتوصيل');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
    // إرسال للتوصيل (Dispatch)
    public function reject($id): JsonResponse
    {
        try {
            $order = $this->workflowService->rejectByManager($id);
            return Response::Success($order, 'تم رفض الطلب');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }

    // تأكيد البيع النهائي (Sold)
    public function complete($id): JsonResponse
    {
        try {
            $order = $this->workflowService->confirmSale($id);
            return Response::Success($order, 'تم تأكيد عملية البيع بنجاح');
        } catch (Throwable $th) {
            return Response::Error([], $th->getMessage());
        }
    }
}