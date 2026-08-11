<?php

namespace App\Services;

use App\Models\ItemTrackingLog;
use App\Models\InventoryAudit;
use Illuminate\Support\Facades\Auth;

class ItemTrackingService
{
    // public function getTrackingLogs()
    // {
    //     return ItemTrackingLog::all();
    // }
public function getTrackingLogs($filters = [], $perPage = 50)
    {
        $query = ItemTrackingLog::query();

        // 1. تحديد النطاق الزمني (من - إلى)
        // إذا لم يتم تمرير تاريخ، نجلب بيانات آخر شهر افتراضياً
        $dateFrom = isset($filters['date_from']) 
                    ? \Carbon\Carbon::parse($filters['date_from'])->startOfDay() 
                    : now()->subMonth()->startOfDay();

        $dateTo = isset($filters['date_to']) 
                  ? \Carbon\Carbon::parse($filters['date_to'])->endOfDay() 
                  : now()->endOfDay();

        $query->whereBetween('created_at', [$dateFrom, $dateTo]);

        // 2. الفلترة حسب المادة (إذا تم إرسالها)
        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        // 3. الفلترة حسب نوع الحركة (إذا تم إرسالها)
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // ترتيب النتائج من الأحدث للأقدم
        $query->orderBy('created_at', 'desc');

        // ملاحظة: نستخدم get() لكي تتوافق مع طريقة قراءة البيانات الحالية في الفلاتر
        // الأفضل مستقبلاً استخدام paginate($perPage) إذا كانت بيانات الشهر الواحد كبيرة جداً
        return $query->get(); 
    }

    /**
 * إنشاء سجل تتبع لعملية توريد المواد المنتجة من خط الإنتاج إلى المستودع
 */
public function logProductionReceipt($shipmentItem, $productionOrder, $item, $quantity, $warehouseUser)
{
    return ItemTrackingLog::create([
        'type' => 'توريد', // حركة دخول للمستودع
        'trackable_id' => $shipmentItem->id, // الدفعة الجديدة التي تم إنشاؤها
        'trackable_type' => get_class($shipmentItem),
        'status' => $productionOrder->status, // الحالة (completed)
        'item_id' => $item->id,
        'item_name' => $item->name,
        'quantity' => $quantity,
        'shipment_id' => null, // لا يوجد شحنة استيراد لأنها تصنيع داخلي
        
        // الجهة المرسلة: قسم الإنتاج
        'sent_from_role' => 'production',
        'sent_from_user_name' => $productionOrder->productionKey->name ?? 'Production Department',
        'sent_from_user_id' => $productionOrder->production_id ?? 0,
        
        // الجهة المستقبلة: أمين المستودع الذي أكد الاستلام والتعبئة
        'sent_to_role' => $warehouseUser->roles->first()->name ?? 'warehouse',
        'sent_to_user_name' => $warehouseUser->name,
        'sent_to_user_id' => $warehouseUser->id,
        
        'notes' => "تم استلام الدفعة الجاهزة الناتجة عن أمر الإنتاج رقم #{$productionOrder->id}"
    ]);
}
    /**
     * Create tracking log for صرف (production)
     */

    public function logProductionIssue($productionOrder, $item, $quantity, $user, $notes = null)
    {
        return ItemTrackingLog::create([
            'type' => 'صرف',
            'trackable_id' => $productionOrder->id,
            'trackable_type' => get_class($productionOrder),
            'status' => $productionOrder->status, // Production status
            'item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'shipment_id' => null,
            'sent_from_role' => $user->roles->first()->name ?? 'warehouse',
            'sent_from_user_name' => $user->name,
            'sent_from_user_id' => $user->id,
            'sent_to_role' => 'production',
            'sent_to_user_name' => 'Production Department',
            'sent_to_user_id' => 0,
            'notes' => $notes ?? 'Materials issued for production'
        ]);
    }

    /**
     * Create tracking log for توريد (shipment receiving)
     */
    public function logShipmentReceipt($shipmentItem, $shipment, $item, $quantity, $fromUser, $toUser = null)
    {
        $toUser = $toUser ?? Auth::user();

        return ItemTrackingLog::create([
            'type' => 'توريد',
            'trackable_id' => $shipmentItem->id,
            'trackable_type' => get_class($shipmentItem),
            'status' => $shipment->status, // Shipment status
            'item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'shipment_id' => $shipment->id,
            'sent_from_role' => $fromUser->roles->first()->name ?? 'supplier',
            'sent_from_user_name' => $fromUser->name,
            'sent_from_user_id' => $fromUser->id,
            'sent_to_role' => $toUser->roles->first()->name ?? 'warehouse',
            'sent_to_user_name' => $toUser->name,
            'sent_to_user_id' => $toUser->id,
            'notes' => 'Shipment items received'
        ]);
    }

    /**
     * Create tracking log for اتلاف (demolish)
     */
    public function logDemolish($demolishOrder, $item, $quantity, $user, $notes = null)
    {
        return ItemTrackingLog::create([
            'type' => 'اتلاف',
            'trackable_id' => $demolishOrder->id,
            'trackable_type' => get_class($demolishOrder),
            'status' => $demolishOrder->status,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'shipment_id' => $demolishOrder->shipment_id,
            'sent_from_role' => $user->roles->first()->name ?? 'warehouse',
            'sent_from_user_name' => $user->name,
            'sent_from_user_id' => $user->id,
            'sent_to_role' => 'demolish',
            'sent_to_user_name' => 'Demolish Department',
            'sent_to_user_id' => 0,
            'notes' => $notes ?? "Demolish order: {$demolishOrder->reason}"
        ]);
    }
    /**
 * تسجيل حركة تعديل مخزني ناتج عن عملية جرد
 */
public function logInventoryAudit($auditItem, $user, $notes = null)
{
    return ItemTrackingLog::create([
        'type' => 'جرد',
        'trackable_id' => $auditItem->inventory_audit_id,
        'trackable_type' => InventoryAudit::class,
        'status' => 'approved',
        'item_id' => $auditItem->item_id,
        'item_name' => $auditItem->item->name ?? 'غير معروف',
        'quantity' => $auditItem->actual_quantity, // الكمية الجديدة اعتماداً
        'shipment_id' => $auditItem->shipmentItem->shipment_id ?? null,
        
        'sent_from_role' => $user->roles->first()->name ?? 'admin',
        'sent_from_user_name' => $user->name,
        'sent_from_user_id' => $user->id,
        
        'sent_to_role' => 'warehouse',
        'sent_to_user_name' => 'المستودع الرئيسي',
        'sent_to_user_id' => 0,
        
        'notes' => $notes ?? "تعديل جرد: الكمية القديمة ({$auditItem->old_quantity}) -> الكمية الجديدة ({$auditItem->actual_quantity}) - الفرق: ({$auditItem->difference})"
    ]);
}

}
