<?php

namespace App\Services\Production;

use App\Models\Item;
use App\Models\BOM;
use App\Models\ProductionOrder;
use Illuminate\Validation\ValidationException;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProductionLogAction;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    public function __construct(
        protected ProductionLogService $logService
    ) {}

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $finalItem = Item::findOrFail($data['item_id']);

            if ($finalItem->is_raw_material == 1) {
                throw ValidationException::withMessages([
                    'item_id' => 'لا يمكن تقديم طلب إنتاج لمادة خام.'
                ]);
            }

            $boms = BOM::where('final_item_id', $finalItem->id)->get();
            if ($boms->isEmpty()) {
                throw ValidationException::withMessages([
                    'item_id' => 'هذا المنتج ليس له تركيبة (BOM) معرفة.'
                ]);
            }

            $primaryBom = $boms->where('is_primary', true)->first();
            if (!$primaryBom) {
                throw ValidationException::withMessages([
                    'bom' => 'يرجى تحديد مادة أساسية واحدة في تركيبة الـ BOM.'
                ]);
            }

            // 1. جلب وحجز أقدم دفعة للمادة الأساسية فقط
            $oldestPrimaryBatch = ShipmentItem::where('item_id', $primaryBom->basic_item_id)
                ->whereRaw('(quantity_received - quantity_reserved) > 0') 
                ->orderBy('expiry_date', 'asc')
                ->lockForUpdate() 
                ->first();

            if (!$oldestPrimaryBatch) {
                throw ValidationException::withMessages([
                    'inventory' => 'لا يوجد رصيد غير محجوز من المادة الأساسية للبدء.'
                ]);
            }

            $availablePrimaryQuantity = (float) ($oldestPrimaryBatch->quantity_received - $oldestPrimaryBatch->quantity_reserved);
            $multiplier = $availablePrimaryQuantity / $primaryBom->basic_item_quantity;
            $expectedFinalQuantity = $multiplier * $primaryBom->final_item_quantity;

            // 2. التحقق فقط من توفر المواد الثانوية إجمالاً (بدون حجز)
            foreach ($boms as $bom) {
                if ($bom->id === $primaryBom->id) continue;

                $neededQuantity = $bom->basic_item_quantity * $multiplier;
                
                $totalAvailableSecondary = ShipmentItem::where('item_id', $bom->basic_item_id)
                    ->selectRaw('SUM(quantity_received - quantity_reserved) as net_available')
                    ->value('net_available') ?? 0;

                if ($totalAvailableSecondary < $neededQuantity) {
                    throw ValidationException::withMessages([
                        'inventory' => "المخزون غير المحجوز من المادة الثانوية ({$bom->basic_item_id}) غير كافٍ. المطلوب: {$neededQuantity}"
                    ]);
                }
            }

            // $warehouseId = $data['warehouse_id'] ?? $oldestPrimaryBatch->warehouse_id ?? null;
            // if (!$warehouseId) {
            //     throw ValidationException::withMessages(['warehouse_id' => 'يجب تحديد المستودع.']);
            // }

            // 3. إنشاء أمر الإنتاج
            $order = ProductionOrder::create([
                'type'          => 'production',
                'production_id' => auth()->id(),
                'item_id'       => $finalItem->id,
                'quantity'      => $expectedFinalQuantity, 
                'status'        => ProductionStatusEnum::APPROVED_BY_MANAGER->value,
                'warehouse_id'  => $data['warehouse_id'] ?? null,
                'notes'         => $data['notes'] ?? null,
            ]);

            // 4. توزيع المواد: المادة الأساسية (تُحجز) والمواد الثانوية (تُسجل كاحتياج فقط)
            foreach ($boms as $bom) {
                $needed = $bom->basic_item_quantity * $multiplier;

                if ($bom->id === $primaryBom->id) {
                    // حجز فعلي للدفعة الخاصة بالمادة الأساسية
                    $oldestPrimaryBatch->increment('quantity_reserved', $needed);

                    $order->materials()->create([
                        'item_id'           => $bom->basic_item_id,
                        'shipment_item_id'  => $oldestPrimaryBatch->id,
                        'required_quantity' => $needed,
                        'consumed_quantity' => 0 
                    ]);
                } else {
                    // 💡 تسجيل احتياج المادة الثانوية ليتعامل معها المستودع لاحقاً (shipment_item_id = null)
                    $order->materials()->create([
                        'item_id'           => $bom->basic_item_id,
                        'shipment_item_id'  => null, 
                        'required_quantity' => $needed,
                        'consumed_quantity' => 0
                    ]);
                }
            }

            $this->logService->log($order, ProductionLogAction::CREATED->value);

            return $order;
        });
    }
}






// namespace App\Services\Production;

// use App\Models\Item;
// use App\Models\BOM;
// use App\Models\ProductionOrder;
// use Illuminate\Validation\ValidationException;
// use App\Enums\ProductionStatusEnum;
// use App\Enums\ProductionLogAction;
// use App\Models\ShipmentItem;
// use Illuminate\Support\Facades\DB;
// class ProductionOrderService
// {
//     public function __construct(
//         protected ProductionLogService $logService
//     ) {}
// // public function create(array $data)
// //     {
// //         $finalItem = Item::findOrFail($data['item_id']);

// //         if ($finalItem->is_raw_material == 1) {
// //             throw ValidationException::withMessages([
// //                 'item_id' => 'لا يمكن تقديم طلب إنتاج لمادة خام.'
// //             ]);
// //         }

// //         // 1. جلب مكونات الـ BOM
// //         $boms = BOM::where('final_item_id', $finalItem->id)->get();

// //         if ($boms->isEmpty()) {
// //             throw ValidationException::withMessages([
// //                 'item_id' => 'هذا المنتج ليس له تركيبة (BOM) معرفة.'
// //             ]);
// //         }

// //         // 2. تحديد المادة الأساسية (يجب أن تكون قد أضفت حقل is_primary في الداتا بيز كما اتفقنا)
// //         $primaryBom = $boms->where('is_primary', true)->first();
// //         if (!$primaryBom) {
// //             throw ValidationException::withMessages([
// //                 'bom' => 'يرجى تحديد مادة أساسية واحدة على الأقل في تركيبة هذا المنتج (BOM).'
// //             ]);
// //         }

// //         // 3. البحث عن أقدم دفعة من المادة الأساسية ستنتهي صلاحيتها (FEFO)
// //         $oldestPrimaryBatch = ShipmentItem::where('item_id', $primaryBom->basic_item_id)
// //             ->where('quantity_received', '>', 0) 
// //             ->orderBy('expiry_date', 'asc')
// //             ->first();

// //         if (!$oldestPrimaryBatch) {
// //             throw ValidationException::withMessages([
// //                 'inventory' => 'لا يوجد أي رصيد من المادة الأساسية للبدء بعملية الإنتاج.'
// //             ]);
// //         }

// //         // 4. حساب المعامل والكميات
// //         $primaryBatchQuantity = (float) $oldestPrimaryBatch->quantity_received;
// //         $multiplier = $primaryBatchQuantity / $primaryBom->basic_item_quantity;
        
// //         // الكمية المتوقع إنتاجها من هذا الأمر
// //         $expectedFinalQuantity = $multiplier * $primaryBom->final_item_quantity;

// //         // التحقق من توفر المواد الثانوية قبل بدء السحب
// //         foreach ($boms as $bom) {
// //             if ($bom->id === $primaryBom->id) continue;

// //             $neededQuantity = $bom->basic_item_quantity * $multiplier;
// //             $totalAvailableSecondary = ShipmentItem::where('item_id', $bom->basic_item_id)
// //                 ->sum('quantity_received');

// //             if ($totalAvailableSecondary < $neededQuantity) {
// //                 throw ValidationException::withMessages([
// //                     'inventory' => "الكمية المتوفرة من المادة الثانوية (رقم {$bom->basic_item_id}) غير كافية لتصنيع كامل الدفعة. المطلوب: {$neededQuantity}"
// //                 ]);
// //             }
// //         }

// //         // 5. إنشاء الطلب وسحب المواد داخل Transaction لضمان سلامة البيانات
// //         return DB::transaction(function () use ($finalItem, $expectedFinalQuantity, $data, $boms, $primaryBom, $oldestPrimaryBatch, $multiplier) {
            
// //             // إنشاء أمر الإنتاج بالكمية المتوقعة بدلاً من الكمية المدخلة يدوياً
// //             $order = ProductionOrder::create([
// //                 'type'          => 'production',
// //                 'production_id' => auth()->id(),
// //                 'item_id'       => $finalItem->id,
// //                 'quantity'      => $expectedFinalQuantity, 
// //                 'status'        => ProductionStatusEnum::APPROVED_BY_MANAGER->value,
// //                 'notes'         => $data['notes'] ?? null,
// //             ]);

// //             // سحب المواد وتوثيقها
// //             foreach ($boms as $bom) {
// //                 $needed = $bom->basic_item_quantity * $multiplier;

// //                 if ($bom->id === $primaryBom->id) {
// //                     // سحب المادة الأساسية من الدفعة الأقدم
// //                     $oldestPrimaryBatch->decrement('quantity_received', $needed);
// //                     $order->materials()->create([
// //                         'item_id'           => $bom->basic_item_id,
// //                         'shipment_item_id'  => $oldestPrimaryBatch->id,
// //                         'required_quantity' => $needed,
// //                         'consumed_quantity' => 0 
// //                     ]);
// //                 } else {
// //                     // سحب المواد الثانوية من دفعاتها الأقدم فالأحدث
// //                     $secondaryBatches = ShipmentItem::where('item_id', $bom->basic_item_id)
// //                         ->where('quantity_received', '>', 0)
// //                         ->orderBy('expiry_date', 'asc')
// //                         ->lockForUpdate()
// //                         ->get();

// //                     foreach ($secondaryBatches as $batch) {
// //                         if ($needed <= 0) break;

// //                         $take = min((float)$batch->quantity_received, $needed);
// //                         if ($take > 0) {
// //                             $batch->decrement('quantity_received', $take);
// //                             $order->materials()->create([
// //                                 'item_id'           => $bom->basic_item_id,
// //                                 'shipment_item_id'  => $batch->id,
// //                                 'required_quantity' => $take,
// //                                 'consumed_quantity' => 0
// //                             ]);
// //                             $needed -= $take;
// //                         }
// //                     }
// //                 }
// //             }

// //             // تسجيل حركة الإنتاج
// //             $this->logService->log($order, ProductionLogAction::CREATED->value);

// //             return $order;
// //         });
// //     }
//     // public function create(array $data)
//     // {
//     //     $item = Item::findOrFail($data['item_id']);

//     //     if ($item->is_raw_material == 1) {

//     //         throw ValidationException::withMessages([
//     //             'item_id' =>
//     //                 'Cannot produce raw material'
//     //         ]);
//     //     }

//     //     $bomExists = BOM::where(
//     //         'final_item_id',
//     //         $item->id
//     //     )->exists();

//     //     if (!$bomExists) {

//     //         throw ValidationException::withMessages([
//     //             'item_id' =>
//     //                 'Item has no BOM'
//     //         ]);
//     //     }

//     //     $order = ProductionOrder::create([
//     //         'type'          => 'production', // 💡 التعديل: إسناد النوع بشكل صريح
//     //         'production_id' => auth()->id(),
//     //         'item_id' => $item->id,
//     //         'quantity' => $data['quantity'],
//     //         'status' => ProductionStatusEnum::PENDING->value,
//     //         'notes' => $data['notes'] ?? null,
//     //     ]);

//     //     $this->logService->log(
//     //         $order,
//     //         ProductionLogAction::CREATED->value
//     //     );

//     //     return $order;
//     // }


//     }
