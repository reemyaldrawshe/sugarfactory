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
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;


class ProductionOrderService
{
    // public function __construct(
    //     protected ProductionLogService $logService
    // ) {}
protected $logService;
    protected $notificationService;

    public function __construct(
        ProductionLogService $logService,
        NotificationService $notificationService
    ) {
        $this->logService = $logService;
        $this->notificationService = $notificationService;
    }
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
 $targetUsers = $this->getUsersByRoles(['warehouse', 'production']);

            $this->notifyUsers(
                $targetUsers,
                'تم انشاء امر انتاج جديد',
                "المدير انشأ امر انتاج جديد رقم #{$order->id} وهو جاهز للبدء بالتحضير",
               'ProductionOrderService',
                ['order_id' => $order->id,
                'action'=>'create'
                ]
            );
            return $order;
        });
    }
    private function getUsersByRoles(array $roles)
    {
        if (method_exists(User::class, 'scopeRole')) {
            return User::role($roles)->get();
        }

        return User::whereIn('role', $roles)->get();
    }

    /**
     * 📩 حفظ الإشعار في قاعدة البيانات وإرساله للمستخدمين عبر NotificationService
     */
    private function notifyUsers($users, string $title, string $message, string $type = 'shipment', array $extraData = [])
    {
        if (!$users || (is_countable($users) && count($users) === 0)) {
            return;
        }

        if ($users instanceof User) {
            $users = collect([$users]);
        }

        foreach ($users as $user) {
            try {
                // استدعاء NotificationService لتقوم بالحفظ في الداتا بيز والإرسال إلى Firebase معاً
                $this->notificationService->send($user, $title, $message, $type, $extraData);
            } catch (\Throwable $e) {
                Log::error("Failed to send notification to user ID {$user->id}: " . $e->getMessage());
            }
        }
    }
}
// namespace App\Services\Warehouse;

// use App\Models\ProductionOrder;
// use App\Models\User;
// use App\Models\ShipmentItem;
// use App\Models\ProductionOrderMaterial;
// use App\Enums\ProductionStatusEnum;
// use App\Services\Production\ProductionLogService;
// use App\Services\NotificationService;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Validation\ValidationException;

// class ProductionService
// {
//     public function __construct(
//         protected ProductionLogService $logService,
//         protected NotificationService $notificationService
//     ) {}

//     /**
//      * أمين المستودع يكبس زر "تأكيد تجهيز المواد"
//      * هنا يتم تطبيق الـ FEFO وحجز المواد الثانوية من المستودع
//      */
//     public function reserveMaterials($id)
//     {
//         return DB::transaction(function () use ($id) {
//             $order = ProductionOrder::with('materials')->findOrFail($id);

//             if ($order->status !== ProductionStatusEnum::APPROVED_BY_MANAGER->value) {
//                 throw ValidationException::withMessages([
//                     'status' => 'الطلب ليس في حالة تسمح بتحضيره حالياً.'
//                 ]);
//             }

//             // 💡 جلب المواد الثانوية فقط (التي لم يتم تحديد دفعتها وقت الإنشاء)
//             $unassignedMaterials = $order->materials->whereNull('shipment_item_id');

//             foreach ($unassignedMaterials as $unassignedMat) {
//                 $neededQuantity = (float) $unassignedMat->required_quantity;

//                 // جلب الدفعات المتاحة للمادة الثانوية (FEFO)
//                 $availableBatches = ShipmentItem::where('item_id', $unassignedMat->item_id)
//                     ->whereRaw('(quantity_received - quantity_reserved) > 0')
//                     ->orderBy('expiry_date', 'asc')
//                     ->lockForUpdate()
//                     ->get();

//                 // التأكد النهائي من توفر الكمية قبل بدء السحب
//                 $totalNetAvailable = $availableBatches->sum(function($b) {
//                     return (float)$b->quantity_received - (float)$b->quantity_reserved;
//                 });

//                 if ($totalNetAvailable < $neededQuantity) {
//                     throw ValidationException::withMessages([
//                         'inventory' => "نعتذر، المخزون المتاح من المادة ({$unassignedMat->item_id}) لم يعد كافياً وقت التحضير."
//                     ]);
//                 }

//                 // سحب وحجز الكمية من الدفعات
//                 foreach ($availableBatches as $batch) {
//                     if ($neededQuantity <= 0) break;

//                     $batchAvailable = $batch->quantity_received - $batch->quantity_reserved;
//                     $take = min((float)$batchAvailable, $neededQuantity);

//                     if ($take > 0) {
//                         // 1. زيادة الكمية المحجوزة في الدفعة
//                         $batch->increment('quantity_reserved', $take);

//                         // 2. إنشاء سجل جديد يربط الكمية المأخوذة بهذه الدفعة تحديداً
//                         $order->materials()->create([
//                             'item_id'           => $unassignedMat->item_id,
//                             'shipment_item_id'  => $batch->id,
//                             'required_quantity' => $take,
//                             'consumed_quantity' => 0
//                         ]);

//                         $neededQuantity -= $take;
//                     }
//                 }

//                 // 3. حذف سجل الاحتياج المبدئي لأنه تم تحويله لسجلات مفصلة بدفعات
//                 $unassignedMat->delete();
//             }

//             // تحديث الحالة إلى "تم تحضير المواد" في المستودع
//             $order->update([
//                 'warehouse_id' => auth()->id(),
//                 'status'       => ProductionStatusEnum::MATERIALS_RESERVED->value,
//             ]);

//             $this->logService->log($order, 'materials_reserved', 'قام أمين المستودع بسحب وتجهيز دفعات المواد الثانوية للإنتاج.');

//             return $order;
//         });
//     }

//     /**
//      * تأكيد الصرف والتسليم الفعلي إلى قسم الإنتاج (هنا يحدث الخصم الفعلي وتصفير الحجز)
//      */
//     public function sendToProduction($id)
//     {
//         return DB::transaction(function () use ($id) {
//             $order = ProductionOrder::with('materials.shipmentItem')->findOrFail($id);

//             if ($order->status !== ProductionStatusEnum::MATERIALS_RESERVED->value) {
//                 throw ValidationException::withMessages([
//                     'status' => 'يجب تحضير وتجهيز المواد أولاً قبل صرفها.'
//                 ]);
//             }

//             // الخصم الفعلي وتحرير الحجز من الدفعات (المادة الأساسية والثانوية معاً)
//             foreach ($order->materials as $material) {
//                 $batch = $material->shipmentItem;
                
//                 if ($batch) {
//                     $batch->decrement('quantity_received', $material->required_quantity);
//                     $batch->decrement('quantity_reserved', $material->required_quantity);
//                 }
//             }

//             // تحديث حالة الطلب والكميات المستهلكة
//             $order->update([
//                 'status' => ProductionStatusEnum::SENT_TO_PRODUCTION->value
//             ]);

//             $order->materials()->update(['consumed_quantity' => DB::raw('required_quantity')]);

//             $this->logService->log($order, 'sent_to_production', 'تم خروج جميع المواد فعلياً من المستودع وتحرير الحجوزات.');

//             // 🔔 إرسال إشعار لقسم الإنتاج باستلام المواد للبدء بالعملية
//             $productionUsers = $this->getUsersByRoles(['production']);
//             $this->notifyUsers(
//                 $productionUsers,
//                 'تسليم مواد الإنتاج',
//                 "تم صرف وتجهيز جميع المواد الأولية لأمر الإنتاج رقم #{$order->id} وتسليمها لخط الإنتاج",
//                 'materials_sent_to_production',
//                 ['production_order_id' => $order->id]
//             );

//             return $order;
//         });
//     }

//     /**
//      * 🔍 جلب المستخدمين حسب الأدوار
//      */
//     private function getUsersByRoles(array $roles)
//     {
//         if (method_exists(User::class, 'scopeRole')) {
//             return User::role($roles)->get();
//         }

//         return User::whereIn('role', $roles)->get();
//     }

//     /**
//      * 📩 إرسال الإشعار وحفظه عبر NotificationService
//      */
//     private function notifyUsers($users, string $title, string $message, string $type = 'production', array $extraData = [])
//     {
//         if (!$users || (is_countable($users) && count($users) === 0)) {
//             return;
//         }

//         if ($users instanceof User) {
//             $users = collect([$users]);
//         }

//         foreach ($users as $user) {
//             try {
//                 $this->notificationService->send($user, $title, $message, $type, $extraData);
//             } catch (\Throwable $e) {
//                 Log::error("Failed to send notification to user ID {$user->id}: " . $e->getMessage());
//             }
//         }
//     }
// }



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
