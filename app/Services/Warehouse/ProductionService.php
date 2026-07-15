<?php

namespace App\Services\Warehouse;

use App\Models\ProductionOrder;
use App\Enums\ProductionStatusEnum;
use App\Services\Production\ProductionLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\ShipmentItem;
use App\Models\ProductionOrderMaterial;

class ProductionService
{
    public function __construct(
        protected ProductionLogService $logService
    ) {}

    /**
     * أمين المستودع يكبس زر "تأكيد تجهيز المواد"
     * هنا يتم تطبيق الـ FEFO وحجز المواد الثانوية من المستودع
     */
    public function reserveMaterials($id)
    {
        return DB::transaction(function () use ($id) {
            $order = ProductionOrder::with('materials')->findOrFail($id);

            if ($order->status !== ProductionStatusEnum::APPROVED_BY_MANAGER->value) {
                throw ValidationException::withMessages([
                    'status' => 'الطلب ليس في حالة تسمح بتحضيره حالياً.'
                ]);
            }

            // 💡 جلب المواد الثانوية فقط (التي لم يتم تحديد دفعتها وقت الإنشاء)
            $unassignedMaterials = $order->materials->whereNull('shipment_item_id');

            foreach ($unassignedMaterials as $unassignedMat) {
                $neededQuantity = (float) $unassignedMat->required_quantity;

                // جلب الدفعات المتاحة للمادة الثانوية (FEFO)
                $availableBatches = ShipmentItem::where('item_id', $unassignedMat->item_id)
                    ->whereRaw('(quantity_received - quantity_reserved) > 0')
                    ->orderBy('expiry_date', 'asc')
                    ->lockForUpdate()
                    ->get();

                // التأكد النهائي من توفر الكمية قبل بدء السحب
                $totalNetAvailable = $availableBatches->sum(function($b) {
return (float)$b->quantity_received - (float)$b->quantity_reserved;
                });

                if ($totalNetAvailable < $neededQuantity) {
                    throw ValidationException::withMessages([
                        'inventory' => "نعتذر، المخزون المتاح من المادة ({$unassignedMat->item_id}) لم يعد كافياً وقت التحضير."
                    ]);
                }

                // سحب وحجز الكمية من الدفعات
                foreach ($availableBatches as $batch) {
                    if ($neededQuantity <= 0) break;

                    $batchAvailable = $batch->quantity_received - $batch->quantity_reserved;
                    $take = min((float)$batchAvailable, $neededQuantity);

                    if ($take > 0) {
                        // 1. زيادة الكمية المحجوزة في الدفعة
                        $batch->increment('quantity_reserved', $take);

                        // 2. إنشاء سجل جديد يربط الكمية المأخوذة بهذه الدفعة تحديداً
                        $order->materials()->create([
                            'item_id'           => $unassignedMat->item_id,
                            'shipment_item_id'  => $batch->id,
                            'required_quantity' => $take,
                            'consumed_quantity' => 0
                        ]);

                        $neededQuantity -= $take;
                    }
                }

                // 3. حذف سجل الاحتياج المبدئي لأنه تم تحويله لسجلات مفصلة بدفعات
                $unassignedMat->delete();
            }

            // تحديث الحالة إلى "تم تحضير المواد" في المستودع
            $order->update([
                'warehouse_id' => auth()->id(),
                'status'       => ProductionStatusEnum::MATERIALS_RESERVED->value,
            ]);

            $this->logService->log($order, 'materials_reserved', 'قام أمين المستودع بسحب وتجهيز دفعات المواد الثانوية للإنتاج.');

            return $order;
        });
    }

    /**
     * تأكيد الصرف والتسليم الفعلي إلى قسم الإنتاج (هنا يحدث الخصم الفعلي وتصفير الحجز)
     */
    public function sendToProduction($id)
    {
        return DB::transaction(function () use ($id) {
            $order = ProductionOrder::with('materials.shipmentItem')->findOrFail($id);

            if ($order->status !== ProductionStatusEnum::MATERIALS_RESERVED->value) {
                throw ValidationException::withMessages([
                    'status' => 'يجب تحضير وتجهيز المواد أولاً قبل صرفها.'
                ]);
            }

            // الخصم الفعلي وتحرير الحجز من الدفعات (المادة الأساسية والثانوية معاً)
            foreach ($order->materials as $material) {
                $batch = $material->shipmentItem;
                
                if ($batch) {
                    $batch->decrement('quantity_received', $material->required_quantity);
                    $batch->decrement('quantity_reserved', $material->required_quantity);
                }
            }

            // تحديث حالة الطلب والكميات المستهلكة
            $order->update([
                'status' => ProductionStatusEnum::SENT_TO_PRODUCTION->value
            ]);

            $order->materials()->update(['consumed_quantity' => DB::raw('required_quantity')]);

            $this->logService->log($order, 'sent_to_production', 'تم خروج جميع المواد فعلياً من المستودع وتحرير الحجوزات.');

            return $order;
        });
    }
}

// // namespace App\Services\Warehouse;

// // use App\Models\ProductionOrder;
// // use App\Models\BOM;
// // use App\Services\ItemTrackingService;
// // use App\Services\Production\Inventory\InventoryService;
// // use App\Enums\ProductionStatusEnum;
// // use App\Services\Production\ProductionLogService;
// // use Illuminate\Support\Facades\DB;
// // use Illuminate\Validation\ValidationException;
// // use App\Models\ShipmentItem;
// // use App\Models\ProductionOrderMaterial;
// // use App\Models\ProductionOrderLog;
// // class ProductionService
// // {
    
// //     protected $trackingService;



// // // // Update constructor
// //     public function __construct(
// //         InventoryService $inventoryService,
// //         ProductionLogService $logService,
// //         ItemTrackingService $trackingService
// //     ) {
// //         $this->inventoryService = $inventoryService;
// //         $this->logService = $logService;
// //         $this->trackingService = $trackingService;
// //     }

// // /**
// //      * كبس زر "بدء التحضير" من أمين المستودع وتوزيع الكميات بناءً على صلاحية الدفعات FEFO
// //      */
// //     public function reserveMaterials($id)
// //     {
// //         return DB::transaction(function () use ($id) {
// //             $order = ProductionOrder::findOrFail($id);

// //             // التأكد من أن حالة الطلب معتمدة من المدير
// //             if ($order->status !== ProductionStatusEnum::APPROVED_BY_MANAGER->value) {
// //                 throw ValidationException::withMessages([
// //                     'status' => 'الطلب يجب أن يكون معتمداً من المدير أولاً لبدء التحضير.'
// //                 ]);
// //             }

// //             // 💡 التعديل: تحديد المواد المطلوبة بناءً على نوع الطلب
// //             $itemsToReserve = [];

// //             if ($order->type === 'production') {
// //                 // إذا كان إنتاج، نجلب المواد من BOM
// //                 $boms = BOM::where('final_item_id', $order->item_id)->get();
// //                 foreach ($boms as $bom) {
// //                     $itemsToReserve[] = [
// //                         'item_id' => $bom->basic_item_id,
// //                         'required_quantity' => $bom->basic_item_quantity * $order->quantity,
// //                     ];
// //                 }
// //             } else {
// //                 // إذا كان مبيعات، نجلب المواد التي طلبها قسم المبيعات مسبقاً في جدول المواد
// //                 $requestedMaterials = ProductionOrderMaterial::where('production_order_id', $order->id)->get();
// //                 if ($requestedMaterials->isEmpty()) {
// //                     throw ValidationException::withMessages(['items' => 'طلب المبيعات لا يحتوي على مواد.']);
// //                 }
                
// //                 foreach ($requestedMaterials as $mat) {
// //                     $itemsToReserve[] = [
// //                         'item_id' => $mat->item_id,
// //                         'required_quantity' => $mat->required_quantity,
// //                     ];
// //                 }
// //                 // نقوم بحذف السجلات المبدئية لنعيد إنشائها مقسمة حسب دفعات الشحن (FEFO)
// //                 ProductionOrderMaterial::where('production_order_id', $order->id)->delete();
// //             }

// //             // تطبيق نظام الـ FEFO للسحب من المستودع (مشترك للإنتاج والمبيعات)
// //             foreach ($itemsToReserve as $reqItem) {
// //                 $remainingToReserve = $reqItem['required_quantity'];

// //                 $batches = ShipmentItem::where('item_id', $reqItem['item_id'])
// //                     ->orderBy('expiry_date', 'asc')
// //                     ->get();

// //                 foreach ($batches as $batch) {
// //                     if ($remainingToReserve <= 0) break;

// //                     $alreadyReserved = ProductionOrderMaterial::where('shipment_item_id', $batch->id)
// //                         ->sum('required_quantity');

// //                     $availableInBatch = $batch->quantity_received - $alreadyReserved;

// //                     if ($availableInBatch > 0) {
// //                         $takeQuantity = min($remainingToReserve, $availableInBatch);

// //                         ProductionOrderMaterial::create([
// //                             'production_order_id' => $order->id,
// //                             'item_id'             => $reqItem['item_id'],
// //                             'shipment_item_id'    => $batch->id,
// //                             'required_quantity'   => $takeQuantity,
// //                             'consumed_quantity'   => 0
// //                         ]);

// //                         $remainingToReserve -= $takeQuantity;
// //                     }
// //                 }

// //                 if ($remainingToReserve > 0) {
// //                     throw ValidationException::withMessages([
// //                         'quantity' => "المخزون المتوفر من مادة [{$reqItem['item_id']}] غير كافٍ."
// //                     ]);
// //                 }
// //             }

// //             $order->update([
// //                 'warehouse_id' => auth()->id(),
// //                 'status'       => ProductionStatusEnum::MATERIALS_RESERVED->value,
// //             ]);

// //             $this->logService->log($order, 'materials_reserved', 'تم تحضير المواد وتخصيص الدفعات.');

// //             return $order;
// //         });
// //     }
// //     /**
// //      * تأكيد الصرف والتسليم الفعلي إلى قسم الإنتاج
// //      */
// //     public function sendToProduction($id)
// //     {
// //         return DB::transaction(function () use ($id) {
// //             $order = ProductionOrder::findOrFail($id);

// //             if ($order->status !== ProductionStatusEnum::MATERIALS_RESERVED->value) {
// //                 throw ValidationException::withMessages([
// //                     'status' => 'يجب حجز المواد وتحضيرها من قبل المستودع أولاً.'
// //                 ]);
// //             }

// //             // تحديث الحالة لتصبح بعهدة الإنتاج
// //             $order->update([
// //                 'status' => ProductionStatusEnum::SENT_TO_PRODUCTION->value
// //             ]);

// //             // تحديث الكميات المستهلكة لتصبح مساوية للمطلوبة عند خروجها الفعلي لخط الإنتاج
// //             ProductionOrderMaterial::where('production_order_id', $order->id)
// //                 ->update(['consumed_quantity' => DB::raw('required_quantity')]);

// //             $this->logService->log($order, 'sent_to_production', 'تم صرف وتسليم المواد الخام لخطوط الإنتاج.');

// //             return $order;
// //         });
// //     }



// //     public function checkAvailability($id)
// //     {

// //     }
// // }




//     /**
//      * كبس زر "بدء التحضير" من أمين المستودع وتوزيع الكميات بناءً على صلاحية الدفعات FEFO
//      */
//     // public function reserveMaterials($id)
//     // {
//     //     return DB::transaction(function () use ($id) {
//     //         $order = ProductionOrder::findOrFail($id);

//     //         // التأكد من أن حالة الطلب معتمدة من المدير
//     //         if ($order->status !== ProductionStatusEnum::APPROVED_BY_MANAGER->value) {
//     //             throw ValidationException::withMessages([
//     //                 'status' => 'الطلب يجب أن يكون معتمداً من المدير أولاً لبدء التحضير.'
//     //             ]);
//     //         }

//     //         // جلب بنية الـ BOM لمعرفة المواد الأساسية المطلوبة
//     //         $boms = BOM::where('final_item_id', $order->item_id)->get();

//     //         foreach ($boms as $bom) {
//     //             // الكمية الكلية المطلوبة من المادة الخام لهذا الأمر
//     //             $requiredQuantity = $bom->basic_item_quantity * $order->quantity;
//     //             $remainingToReserve = $requiredQuantity;

//     //             // جلب دفعات الشحن الخاصة بهذه المادة الخام، مرتبة حسب تاريخ الانتهاء الأقرب (FEFO)
//     //             // نفترض أن جدول shipment_items يحتوي على (quantity_received و expiry_date) لحساب المتاح
//     //             $batches = ShipmentItem::where('item_id', $bom->basic_item_id)
//     //                 ->orderBy('expiry_date', 'asc')
//     //                 ->get();

//     //             foreach ($batches as $batch) {
//     //                 if ($remainingToReserve <= 0) break;

//     //                 // حساب الكميات المحجوزة سابقاً من هذه الدفعة في جدولك لمعرفة المتبقي الفعلي
//     //                 $alreadyReserved = ProductionOrderMaterial::where('shipment_item_id', $batch->id)
//     //                     ->sum('required_quantity');

//     //                 $availableInBatch = $batch->quantity_received - $alreadyReserved;

//     //                 if ($availableInBatch > 0) {
//     //                     $takeQuantity = min($remainingToReserve, $availableInBatch);

//     //                     // إدراج سجل الحجز في جدول الـ materials الذي أرسلته لي
//     //                     ProductionOrderMaterial::create([
//     //                         'production_order_id' => $order->id,
//     //                         'item_id'             => $bom->basic_item_id,
//     //                         'shipment_item_id'    => $batch->id,
//     //                         'required_quantity'   => $takeQuantity,
//     //                         'consumed_quantity'   => 0 // لا زال في مرحلة التحضير ولم يستهلك بعد
//     //                     ]);

//     //                     $remainingToReserve -= $takeQuantity;
//     //                 }
//     //             }

//     //             // إذا انتهت الدفعات ولم نستطع تغطية الكمية المطلوبة بالكامل
//     //             if ($remainingToReserve > 0) {
//     //                 throw ValidationException::withMessages([
//     //                     'quantity' => "المخزون المتوفر من مادة [{$bom->basic_item_id}] غير كافٍ لتغطية الكمية المطلوبة بناءً على الدفعات المتوفرة."
//     //                 ]);
//     //             }
//     //         }

//     //         // تحديث حالة الأمر وإسناد معرف أمين المستودع الذي قام بالتحضير
//     //         $order->update([
//     //             'warehouse_id' => auth()->id(),
//     //             'status'       => ProductionStatusEnum::MATERIALS_RESERVED->value, // جاهز للاستلام عند الإنتاج
//     //         ]);

//     //         // تسجيل الحركة في الـ Logs بناءً على جدول اللوج الخاص بك
//     //         $this->logService->log($order, 'materials_reserved', 'تم تحضير المواد وتخصيص الدفعات حسب تاريخ انتهاء الصلاحية.');

//     //         return $order;
//     //     });
//     // }

//     // public function reserveMaterials($id)
//     // {
//     //     return DB::transaction(function () use ($id) {

//     //         $order = ProductionOrder::findOrFail($id);

//     //         if (
//     //             $order->status !==
//     //             ProductionStatusEnum::APPROVED_BY_MANAGER->value
//     //         ) {

//     //             throw ValidationException::withMessages([
//     //                 'status' => 'Status should be manager_approved'
//     //             ]);
//     //         }


//     //         $boms = BOM::where(
//     //             'final_item_id',
//     //             $order->item_id
//     //         )->get();

//     //         foreach ($boms as $bom) {

//     //             $required =
//     //                 $bom->basic_item_quantity
//     //                 * $order->quantity;

//     //             if($this->inventoryService->checkAvailability($bom->basic_item_id, $required)){
//     //                 $this->inventoryService->reserveFIFO(
//     //                     $order,
//     //                     $bom->basic_item_id,
//     //                     $required
//     //                 );
//     //             }else{
//     //                 throw ValidationException::withMessages([
//     //                     'status' => 'quantity not efficient'
//     //                 ]);
//     //             }

//     //         }

//     //         $order->update([
//     //             'warehouse_id' => auth()->id(),
//     //             'status' =>
//     //                 ProductionStatusEnum
//     //                 ::MATERIALS_RESERVED
//     //                     ->value,
//     //         ]);

//     //         return $order;
//     //     });
//     // }

//     // public function sendToProduction($id)
//     // {
//     //     $order = ProductionOrder::findOrFail($id);

//     //     if (
//     //         $order->status !==
//     //         ProductionStatusEnum::MATERIALS_RESERVED->value
//     //     ) {
//     //         throw ValidationException::withMessages([
//     //             'status' => 'status should be materials_reserved'
//     //         ]);
//     //     }

//     //     $order->update([
//     //         'status' => ProductionStatusEnum::SENT_TO_PRODUCTION->value
//     //     ]);

//     //     // Add tracking log for each reserved material
//     //     foreach ($order->reservedMaterials as $reserved) {
//     //         $this->trackingService->logProductionIssue(
//     //             $order,
//     //             $reserved->item,
//     //             $reserved->quantity,
//     //             auth()->user(),
//     //             "Materials issued for production order #{$order->id}"
//     //         );
//     //     }

//     //     return $order;
//     // }