<?php

namespace App\Services;

use App\Models\ItemTrackingLog;
use Illuminate\Support\Facades\Auth;

class ItemTrackingService
{
    public function getTrackingLogs()
    {
        return ItemTrackingLog::all();
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

}
