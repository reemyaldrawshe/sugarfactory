<?php

namespace App\Services\Warehouse;

use App\Models\Section;
use Illuminate\Support\Collection;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\ShipmentItem;
use App\Models\Item;
use App\Services\ItemTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class InventoryAuditService
{
    public function __construct(
        protected ItemTrackingService $trackingService
    ){}
    /**
     * جلب بيانات الجرد الهرمية (أقسام -> مواد -> دفعات)
     */
    public function getAuditData(array $filters = []): Collection
    {
        $query = Section::query();

        // 1. الفلترة حسب القسم (إن وجد)
        if (!empty($filters['section_id'])) {
            $query->where('id', $filters['section_id']);
        }

        // 2. التحميل المسبق للمواد والدفعات لمنع مشكلة N+1 Query
        $query->with(['items' => function ($itemQuery) use ($filters) {
            // فلترة حسب المادة
            if (!empty($filters['item_id'])) {
                $itemQuery->where('id', $filters['item_id']);
            }

            // بحث باسم المادة 
            if (!empty($filters['search'])) {
                $itemQuery->where('name', 'like', '%' . $filters['search'] . '%');
            }

            // جلب الوحدة والدفعات المترابطة لكل مادة
            $itemQuery->with(['unit', 'shipmentItems' => function ($batchQuery) {
                // ترتيب الدفعات حسب الأحدث
                $batchQuery->orderBy('created_at', 'desc');
            }]);
        }]);

        $sections = $query->get();

        // 3. إعادة تشكيل البيانات لتنسيق نظيف ومباشر لـ Flutter
        return $sections->map(function ($section) {
            return [
                'id' => $section->id,
                'name' => $section->name, // يقرأ تلقائياً ar_name أو en_name حسب اللغة
                'items' => $section->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'unit_name' => $item->unit_name,
                        'is_raw_material' => (bool) $item->is_raw_material,
                        'old_quantity' => (float) $item->quantity, // الكمية الإجمالية الحالية في النظام
                        'batches' => $item->shipmentItems->map(function ($batch) {
                            return [
                                'id' => $batch->id, // ID الدفعة (shipment_item_id)
                                'shipment_id' => $batch->shipment_id,
                                'production_order_id' => $batch->production_order_id,
                                // تسمية معبرة لمصدر الدفعة
                                'batch_label' => $batch->shipment_id 
                                    ? "شحنة #{$batch->shipment_id}" 
                                    : ($batch->production_order_id ? "إنتاج #{$batch->production_order_id}" : "دفعة #{$batch->id}"),
                                'expiry_date' => $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : null,
                                'expiry_status' => $batch->expiry_status,
                                'unit_price' => (float) $batch->unit_price,
                                'old_quantity' => (float) $batch->quantity_received, // الكمية الحالية للدفعة في النظام
                            ];
                        })->values()
                    ];
                })->values()
            ];
        })->filter(function ($section) {
            // إخفاء الأقسام الفارغة في حال تم تطبيق فلتر يخص مادة معينة
            return $section['items']->isNotEmpty();
        })->values();
    }
    // public function createAuditOrder(array $data)
    // {
    //     return DB::transaction(function () use ($data) {
    //         // إنشاء كود تلقائي للطلب
    //         $code = 'AUD-' . date('Ymd') . '-' . rand(1000, 9999);

    //         $audit = InventoryAudit::create([
    //             'code' => $code,
    //             'status' => 'pending',
    //             'reason' => $data['reason'] ?? 'جرد دوري للمخزون',
    //             'created_by' => Auth::id(),
    //         ]);

    //         foreach ($data['items'] as $singleItem) {
    //             // جلب بيانات الدفعة الحالية لتحديد الكمية القديمة بالنظام
    //             $shipmentItem = ShipmentItem::with('item')->findOrFail($singleItem['shipment_item_id']);

    //             $oldQuantity = (float) $shipmentItem->quantity_received; // أو الحقل المستعمل لكمية الدفعة
    //             $actualQuantity = (float) $singleItem['actual_quantity'];
    //             $difference = $actualQuantity - $oldQuantity;

    //             // حساب درجة التطابق
    //             $matchPercentage = $this->calculateMatchPercentage($oldQuantity, $actualQuantity);

    //             InventoryAuditItem::create([
    //                 'inventory_audit_id' => $audit->id,
    //                 'section_id' => $singleItem['section_id'],
    //                 'item_id' => $shipmentItem->item_id,
    //                 'shipment_item_id' => $shipmentItem->id,
    //                 'old_quantity' => $oldQuantity,
    //                 'actual_quantity' => $actualQuantity,
    //                 'difference' => $difference,
    //                 'match_percentage' => $matchPercentage,
    //                 'notes' => $singleItem['notes'] ?? null,
    //             ]);
    //         }

    //         return $audit->load(['items.section', 'items.item', 'items.shipmentItem', 'creator']);
    //     });
    // }
public function createAuditOrder(array $data)
{
    return DB::transaction(function () use ($data) {
        // إنشاء كود تلقائي للطلب
        $code = 'AUD-' . date('Ymd') . '-' . rand(1000, 9999);

        $audit = InventoryAudit::create([
            'code' => $code,
            'status' => 'pending',
            'reason' => $data['reason'] ?? 'جرد دوري للمخزون',
            'created_by' => Auth::id(),
        ]);

        foreach ($data['items'] as $singleItem) {
            // جلب الدفعة مع المادة المرتبطة بها لمعرفة القسم تلقائياً
            $shipmentItem = ShipmentItem::with('item')->findOrFail($singleItem['shipment_item_id']);

            $oldQuantity = (float) $shipmentItem->quantity_received; // تأكد من اسم الحقل الذي يمثل الكمية الحالية
            $actualQuantity = (float) $singleItem['actual_quantity'];
            $difference = $actualQuantity - $oldQuantity;

            // حساب درجة التطابق
            $matchPercentage = $this->calculateMatchPercentage($oldQuantity, $actualQuantity);

            InventoryAuditItem::create([
                'inventory_audit_id' => $audit->id,
                // استنتاج القسم والمادة برمجياً من الدفعة
                'section_id' => $shipmentItem->item->section_id, 
                'item_id' => $shipmentItem->item_id,             
                
                'shipment_item_id' => $shipmentItem->id,
                'old_quantity' => $oldQuantity,
                'actual_quantity' => $actualQuantity,
                'difference' => $difference,
                'match_percentage' => $matchPercentage,
                'notes' => $singleItem['notes'] ?? null,
            ]);
        }

        return $audit->load(['items.section', 'items.item', 'items.shipmentItem', 'creator']);
    });
}
    /**
     * 2. موافقة الأدمن على طلب الجرد وتطبيق التعديلات في المستودع
     */
    public function approveAuditOrder($id)
    {
        return DB::transaction(function () use ($id) {
            $audit = InventoryAudit::with(['items.item', 'items.shipmentItem'])->findOrFail($id);

            if ($audit->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'طلب الجرد تم معالجته سابقاً.'
                ]);
            }

            foreach ($audit->items as $auditItem) {
                // أ) تعديل كمية الدفعة نفسها
                $shipmentItem = $auditItem->shipmentItem;
                $shipmentItem->quantity_received = $auditItem->actual_quantity;
                $shipmentItem->save();

                // ب) إعادة حساب إجمالي كمية المادة الكلية بالمستودع
                // $item = $auditItem->item;
                // $totalNewQuantity = ShipmentItem::where('item_id', $item->id)->sum('quantity_received');
                // $item->quantity = $totalNewQuantity;
                // $item->save();

                // ج) تسجيل التغير في سجل حركة المواد
                $this->trackingService->logInventoryAudit(
                    $auditItem,
                    Auth::user(),
                    "اعتماد جرد للدفعة #{$shipmentItem->id}"
                );
            }

            // تحديث حالة الطلب
            $audit->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $audit;
        });
    }

    /**
     * 3. رفض طلب الجرد
     */
    public function rejectAuditOrder($id, string $rejectionReason)
    {
        $audit = InventoryAudit::findOrFail($id);

        if ($audit->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'طلب الجرد تم معالجته سابقاً.'
            ]);
        }

        $audit->update([
            'status' => 'rejected',
            'rejection_reason' => $rejectionReason,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $audit;
    }

    /**
     * 4. جلب جميع طلبات الجرد بمعلومات شاملة ومفصلة
     */
    /**
     * 4. جلب جميع طلبات الجرد بمعلومات شاملة ومفصلة ومخصصة
     */
    public function getAuditOrders(array $filters = [], $perPage = 15)
    {
        $query = InventoryAudit::with([
            'creator:id,name',
            'approver:id,name',
            'items.section:id,en_name,ar_name', // جلب القسم
            'items.item:id,name,unit_id',       // جلب المادة فقط بالاعمدة الاساسية
            'items.item.unit:id,name',          // جلب الوحدة
            'items.shipmentItem:id,shipment_id,production_order_id,created_at' // جلب الدفعة
        ])->orderBy('created_at', 'desc');

        // الفلاتر
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $audits = $query->get();

        // إعادة تشكيل البيانات لتنظيفها من خصائص Appends الموجودة في موديل Item
        return $audits->map(function ($audit) {
            return [
                'id' => $audit->id,
                'code' => $audit->code,
                'status' => $audit->status,
                'reason' => $audit->reason,
                'rejection_reason' => $audit->rejection_reason,
                'created_by_name' => $audit->creator->name ?? null,
                'approved_by_name' => $audit->approver->name ?? null,
                'created_at' => $audit->created_at ? $audit->created_at->format('Y-m-d H:i') : null,
                'approved_at' => $audit->approved_at ? $audit->approved_at->format('Y-m-d H:i') : null,
                
                // جلب تفاصيل المواد المجرودة بالدقة المطلوبة
                'items' => $audit->items->map(function ($auditItem) {
                    
                    // تحديد اسم القسم بناءً على اللغة أو جلب الاثنين
                    $sectionName = $auditItem->section ? ($auditItem->section->ar_name ?? $auditItem->section->en_name) : null;

                    return [
                        'audit_item_id' => $auditItem->id,
                        
                        // معلومات الدفعة والكميات
                        'shipment_item_id' => $auditItem->shipment_item_id,
                        'old_quantity' => (float) $auditItem->old_quantity,
                        'actual_quantity' => (float) $auditItem->actual_quantity,
                        'difference' => (float) $auditItem->difference,
                        'match_percentage' => (float) $auditItem->match_percentage,
                        'notes' => $auditItem->notes,
                        
                        // معلومات المادة والارتباطات
                        'item_id' => $auditItem->item_id,
                        'item_name' => $auditItem->item->name ?? null,
                        'unit_name' => $auditItem->item->unit->name ?? null,
                        'section_name' => $sectionName,
                    ];
                })->values()
            ];
        });
    }
//     public function getAuditOrders(array $filters = [], $perPage = 15)
//     {
//         $query = InventoryAudit::with([
//             'creator:id,name',
//             'approver:id,name',
//             'items.section:id,en_name,ar_name',
//             'items.item:id,name,unit_id', 
        
//         // جلب الوحدة المرتبطة بالمادة
//             'items.item.unit:id,name',
//             'items.shipmentItem:id,shipment_id,production_order_id,created_at'
//         ])->orderBy('created_at', 'desc');

//         if (!empty($filters['status'])) {
//             $query->where('status', $filters['status']);
//         }

//         if (!empty($filters['code'])) {
//             $query->where('code', 'like', '%' . $filters['code'] . '%');
//         }

//         if (!empty($filters['date_from'])) {
//             $query->whereDate('created_at', '>=', $filters['date_from']);
//         }

//         if (!empty($filters['date_to'])) {
//             $query->whereDate('created_at', '<=', $filters['date_to']);
//         }
//         return $query->get();
// //        return $query->paginate($perPage);
//     }

    /**
     * 5. معادلة حساب نسبة التطابق
     */
    private function calculateMatchPercentage($old, $actual): float
    {
        if ($old == $actual) {
            return 100.00;
        }

        if ($old == 0) {
            return 0.00; // مادة لم تكن موجودة بالنظام وأصبحت موجودة
        }

        $difference = abs($actual - $old);
        $percentage = (1 - ($difference / $old)) * 100;

        return round(max(0, $percentage), 2); // تجنب القيم السلبية
    }

//     public function getAuditOrderById($id): InventoryAudit
// {
//     return InventoryAudit::with([
//         'creator:id,name',
//         'approver:id,name',
//         'items.section:id,en_name,ar_name',
//         'items.item:id,name,unit_id', 
        
//         // جلب الوحدة المرتبطة بالمادة
//         'items.item.unit:id,name',
//         'items.shipmentItem:id,shipment_id,production_order_id,created_at'
//     ])->findOrFail($id);
// }

     /*
     * جلب تفاصيل طلب جرد محدد برقم الـ ID (بيانات نظيفة ومحددة)
     */
    public function getAuditOrderById($id): array
    {
        $audit = InventoryAudit::with([
            'creator:id,name',
            'approver:id,name',
            'items.section:id,en_name,ar_name',
            'items.item:id,name,unit_id', 
            'items.item.unit:id,name',
            'items.shipmentItem:id,shipment_id,production_order_id,created_at'
        ])->findOrFail($id);

        return [
            'id' => $audit->id,
            'code' => $audit->code,
            'status' => $audit->status,
            'reason' => $audit->reason,
            'rejection_reason' => $audit->rejection_reason,
            'created_by_name' => $audit->creator->name ?? null,
            'approved_by_name' => $audit->approver->name ?? null,
            'created_at' => $audit->created_at ? $audit->created_at->format('Y-m-d H:i') : null,
            'approved_at' => $audit->approved_at ? $audit->approved_at->format('Y-m-d H:i') : null,
            
            // تفاصيل المواد والدفعات المجرودة في هذا الأمر
            'items' => $audit->items->map(function ($auditItem) {
                
                $sectionName = $auditItem->section ? ($auditItem->section->ar_name ?? $auditItem->section->en_name) : null;

                return [
                    'audit_item_id' => $auditItem->id,
                    
                    // معلومات الدفعة والكميات قبل وبعد الجرد
                    'shipment_item_id' => $auditItem->shipment_item_id,
                    'old_quantity' => (float) $auditItem->old_quantity,
                    'actual_quantity' => (float) $auditItem->actual_quantity,
                    'difference' => (float) $auditItem->difference,
                    'match_percentage' => (float) $auditItem->match_percentage,
                    'notes' => $auditItem->notes,
                    
                    // معلومات المادة والوحدة والقسم
                    'item_id' => $auditItem->item_id,
                    'item_name' => $auditItem->item->name ?? null,
                    'unit_name' => $auditItem->item->unit->name ?? null,
                    'section_name' => $sectionName,
                ];
            })->values()->toArray()
        ];
    }

}