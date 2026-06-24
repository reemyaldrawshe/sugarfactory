<?php

namespace App\Services\Warehouse;

use App\Models\ProductionOrder;
use App\Models\BOM;
use App\Services\ItemTrackingService;
use App\Services\Production\Inventory\InventoryService;
use App\Enums\ProductionStatusEnum;
use App\Services\Production\ProductionLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\ShipmentItem;
use App\Models\ProductionOrderMaterial;
use App\Models\ProductionOrderLog;
class ProductionService
{
    
    protected $trackingService;



// // Update constructor
    public function __construct(
        InventoryService $inventoryService,
        ProductionLogService $logService,
        ItemTrackingService $trackingService
    ) {
        $this->inventoryService = $inventoryService;
        $this->logService = $logService;
        $this->trackingService = $trackingService;
    }


    /**
     * كبس زر "بدء التحضير" من أمين المستودع وتوزيع الكميات بناءً على صلاحية الدفعات FEFO
     */
    public function reserveMaterials($id)
    {
        return DB::transaction(function () use ($id) {
            $order = ProductionOrder::findOrFail($id);

            // التأكد من أن حالة الطلب معتمدة من المدير
            if ($order->status !== ProductionStatusEnum::APPROVED_BY_MANAGER->value) {
                throw ValidationException::withMessages([
                    'status' => 'الطلب يجب أن يكون معتمداً من المدير أولاً لبدء التحضير.'
                ]);
            }

            // جلب بنية الـ BOM لمعرفة المواد الأساسية المطلوبة
            $boms = BOM::where('final_item_id', $order->item_id)->get();

            foreach ($boms as $bom) {
                // الكمية الكلية المطلوبة من المادة الخام لهذا الأمر
                $requiredQuantity = $bom->basic_item_quantity * $order->quantity;
                $remainingToReserve = $requiredQuantity;

                // جلب دفعات الشحن الخاصة بهذه المادة الخام، مرتبة حسب تاريخ الانتهاء الأقرب (FEFO)
                // نفترض أن جدول shipment_items يحتوي على (quantity_received و expiry_date) لحساب المتاح
                $batches = ShipmentItem::where('item_id', $bom->basic_item_id)
                    ->orderBy('expiry_date', 'asc')
                    ->get();

                foreach ($batches as $batch) {
                    if ($remainingToReserve <= 0) break;

                    // حساب الكميات المحجوزة سابقاً من هذه الدفعة في جدولك لمعرفة المتبقي الفعلي
                    $alreadyReserved = ProductionOrderMaterial::where('shipment_item_id', $batch->id)
                        ->sum('required_quantity');

                    $availableInBatch = $batch->quantity_received - $alreadyReserved;

                    if ($availableInBatch > 0) {
                        $takeQuantity = min($remainingToReserve, $availableInBatch);

                        // إدراج سجل الحجز في جدول الـ materials الذي أرسلته لي
                        ProductionOrderMaterial::create([
                            'production_order_id' => $order->id,
                            'item_id'             => $bom->basic_item_id,
                            'shipment_item_id'    => $batch->id,
                            'required_quantity'   => $takeQuantity,
                            'consumed_quantity'   => 0 // لا زال في مرحلة التحضير ولم يستهلك بعد
                        ]);

                        $remainingToReserve -= $takeQuantity;
                    }
                }

                // إذا انتهت الدفعات ولم نستطع تغطية الكمية المطلوبة بالكامل
                if ($remainingToReserve > 0) {
                    throw ValidationException::withMessages([
                        'quantity' => "المخزون المتوفر من مادة [{$bom->basic_item_id}] غير كافٍ لتغطية الكمية المطلوبة بناءً على الدفعات المتوفرة."
                    ]);
                }
            }

            // تحديث حالة الأمر وإسناد معرف أمين المستودع الذي قام بالتحضير
            $order->update([
                'warehouse_id' => auth()->id(),
                'status'       => ProductionStatusEnum::MATERIALS_RESERVED->value, // جاهز للاستلام عند الإنتاج
            ]);

            // تسجيل الحركة في الـ Logs بناءً على جدول اللوج الخاص بك
            $this->logService->log($order, 'materials_reserved', 'تم تحضير المواد وتخصيص الدفعات حسب تاريخ انتهاء الصلاحية.');

            return $order;
        });
    }

    /**
     * تأكيد الصرف والتسليم الفعلي إلى قسم الإنتاج
     */
    public function sendToProduction($id)
    {
        return DB::transaction(function () use ($id) {
            $order = ProductionOrder::findOrFail($id);

            if ($order->status !== ProductionStatusEnum::MATERIALS_RESERVED->value) {
                throw ValidationException::withMessages([
                    'status' => 'يجب حجز المواد وتحضيرها من قبل المستودع أولاً.'
                ]);
            }

            // تحديث الحالة لتصبح بعهدة الإنتاج
            $order->update([
                'status' => ProductionStatusEnum::SENT_TO_PRODUCTION->value
            ]);

            // تحديث الكميات المستهلكة لتصبح مساوية للمطلوبة عند خروجها الفعلي لخط الإنتاج
            ProductionOrderMaterial::where('production_order_id', $order->id)
                ->update(['consumed_quantity' => DB::raw('required_quantity')]);

            $this->logService->log($order, 'sent_to_production', 'تم صرف وتسليم المواد الخام لخطوط الإنتاج.');

            return $order;
        });
    }



    // public function reserveMaterials($id)
    // {
    //     return DB::transaction(function () use ($id) {

    //         $order = ProductionOrder::findOrFail($id);

    //         if (
    //             $order->status !==
    //             ProductionStatusEnum::APPROVED_BY_MANAGER->value
    //         ) {

    //             throw ValidationException::withMessages([
    //                 'status' => 'Status should be manager_approved'
    //             ]);
    //         }


    //         $boms = BOM::where(
    //             'final_item_id',
    //             $order->item_id
    //         )->get();

    //         foreach ($boms as $bom) {

    //             $required =
    //                 $bom->basic_item_quantity
    //                 * $order->quantity;

    //             if($this->inventoryService->checkAvailability($bom->basic_item_id, $required)){
    //                 $this->inventoryService->reserveFIFO(
    //                     $order,
    //                     $bom->basic_item_id,
    //                     $required
    //                 );
    //             }else{
    //                 throw ValidationException::withMessages([
    //                     'status' => 'quantity not efficient'
    //                 ]);
    //             }

    //         }

    //         $order->update([
    //             'warehouse_id' => auth()->id(),
    //             'status' =>
    //                 ProductionStatusEnum
    //                 ::MATERIALS_RESERVED
    //                     ->value,
    //         ]);

    //         return $order;
    //     });
    // }

    // public function sendToProduction($id)
    // {
    //     $order = ProductionOrder::findOrFail($id);

    //     if (
    //         $order->status !==
    //         ProductionStatusEnum::MATERIALS_RESERVED->value
    //     ) {
    //         throw ValidationException::withMessages([
    //             'status' => 'status should be materials_reserved'
    //         ]);
    //     }

    //     $order->update([
    //         'status' => ProductionStatusEnum::SENT_TO_PRODUCTION->value
    //     ]);

    //     // Add tracking log for each reserved material
    //     foreach ($order->reservedMaterials as $reserved) {
    //         $this->trackingService->logProductionIssue(
    //             $order,
    //             $reserved->item,
    //             $reserved->quantity,
    //             auth()->user(),
    //             "Materials issued for production order #{$order->id}"
    //         );
    //     }

    //     return $order;
    // }

    public function checkAvailability($id)
    {

    }
}
