<?php

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Services\Production\Inventory\InventoryService;
use App\Enums\ProductionStatusEnum;
use Illuminate\Validation\ValidationException;
use App\Models\ShipmentItem;
use App\Services\ItemTrackingService; // 👈 استدعاء خدمة التتبع
use Illuminate\Support\Facades\DB;

use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
class ProductionExecutionService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ProductionLogService $logService,
        protected ItemTrackingService $trackingService, // 👈 حقن خدمة التتبع هنا
        protected    NotificationService $notificationService

    ) {}

    // public function start($id)
    // {
    //     $order = ProductionOrder::findOrFail($id);

    //     if (
    //         $order->status !==
    //         ProductionStatusEnum::SENT_TO_PRODUCTION->value
    //     ) {

    //         throw ValidationException::withMessages([
    //             'status' => 'Invalid status'
    //         ]);
    //     }

    //     $order->update([
    //         'status' =>
    //             ProductionStatusEnum
    //             ::IN_PRODUCTION
    //                 ->value,

    //         'started_at' => now(),
    //     ]);

    //     return $order;
    // }

    protected function ensureIsProductionOrder($order)
    {
        if ($order->type !== 'production') {
            throw ValidationException::withMessages([
                'type' => 'عذراً، هذا الطلب يخص المبيعات ولا يمكن معالجته في قسم الإنتاج.'
            ]);
        }
    }
public function start($id)
    {
        $order = ProductionOrder::findOrFail($id);
         $this->ensureIsProductionOrder($order); // 👈 فحص النوع
        if ($order->status !== ProductionStatusEnum::SENT_TO_PRODUCTION->value) {
            throw ValidationException::withMessages([
                'status' => 'الطلب لم يتم صرفه من المستودع بعد أو أنه بحالة غير صالحة للبدء.'
            ]);
        }

        $order->update([
            'status' => ProductionStatusEnum::IN_PRODUCTION->value,
            'started_at' => now(), // إذا كان لديك هذا الحقل، أو تكتفي بالحالة واللوج
        ]);

        $this->logService->log($order, 'started_production', 'أكد الإنتاج استلام المواد وبدأت عملية التصنيع الفعيلة.');

        return $order;
    }
    public function pause($id)
    {
        $order = ProductionOrder::findOrFail($id);
$this->ensureIsProductionOrder($order); // 👈 فحص النوع
        $order->update([
            'status' =>
                ProductionStatusEnum::PAUSED->value
        ]);

        return $order;
    }

    public function resume($id)
    {
        $order = ProductionOrder::findOrFail($id);
$this->ensureIsProductionOrder($order); // 👈 فحص النوع
        $order->update([
            'status' =>
                ProductionStatusEnum
                ::IN_PRODUCTION
                    ->value
        ]);

        return $order;
    }
public function complete(int $id, $producedQty, $expiryDate, $warehouseUser): ProductionOrder
    {
        // تغليف العملية بالكامل داخل Transaction لضمان الأمان
        return DB::transaction(function () use ($id, $producedQty, $expiryDate, $warehouseUser) {
            
           // $order = ProductionOrder::findOrFail($id);
           $order = ProductionOrder::with('materials.shipmentItem')->findOrFail($id);
            $this->ensureIsProductionOrder($order);

            // التحقق من حالة الطلب الحالية قبل الإغلاق
            if ($order->status === ProductionStatusEnum::COMPLETED->value) {
                throw new \Exception('هذا الأمر مكتمل ومغلق مسبقاً.');
            }

            // 1. حساب الانحراف (الكمية الفعلية المنتجة - الكمية المتوقعة المطلوبة)
            $deviation = $producedQty - $order->quantity;

            // 2. تحديث بيانات أمر الإنتاج الأساسية
            $order->update([
                'produced_quantity' => $producedQty,
                'deviation'         => $deviation,
                'status'            => ProductionStatusEnum::COMPLETED->value,
            ]);
// 💡 التعديل الثاني: حساب إجمالي تكلفة المواد الخام المستهلكة في هذا الأمر
        $totalMaterialsCost = 0;
        foreach ($order->materials as $material) {
            $rawMaterialUnitPrice = $material->shipmentItem ? $material->shipmentItem->unit_price : 0;
            $totalMaterialsCost += ($material->consumed_quantity * $rawMaterialUnitPrice);
        }

        // 💡 التعديل الثالث: حساب تكلفة الوحدة الواحدة من المنتج النهائي
        // حماية الكود من خطأ Division by Zero في حال تم إدخال كمية منتجة = 0 بالخطأ
        $finishedProductUnitPrice = $producedQty > 0 ? ($totalMaterialsCost / $producedQty) : 0;
            // 3. توليد الدفعة الجديدة (Batch) في جدول الـ shipment_items
            // تم ربطها بـ production_order_id لمعرفة مصدرها مستقبلاً
            // $batch = ShipmentItem::create([
            //     'item_id'             => $order->item_id,
            //     'shipment_id'         => null, // فارغ لأن المصدر إنتاج وليس شحنة خارجية
            //     'production_order_id' => $order->id, // 👈 ربط الدفعة بأمر الإنتاج الحالي
            //     'quantity_required'   => $order->quantity??0,
            //     'quantity_received'   => $producedQty,
            //     'quantity_reserved'   => 0,
            //     'unit_price'          => $finishedProductUnitPrice,
            //     'price'              => $totalMaterialsCost, // السعر الكلي = سعر الوحدة * الكمية المنتجة
            //     'expiry_date'         => $expiryDate, // التاريخ المدخل من المستخدم عبر الواجهة
            //     'note'                => "دفعة ناتجة عن إغلاق أمر الإنتاج رقم #{$order->id}",
            // ]);
            $batch = ShipmentItem::create([
    'item_id'             => (int) $order->item_id,
    'shipment_id'         => null,
    'production_order_id' => (int) $order->id,
    'quantity_required'   => (float) ($order->quantity ?? 0),
    'quantity_received'   => (float) $producedQty, // 👈 تحويل صريح لمنع التعارض مع float cast
    'quantity_reserved'   => 0.0,                  // 👈 إسناد float صريح
    'unit_price'          => (float) $finishedProductUnitPrice,
    'price'               => (float) $totalMaterialsCost,
    'expiry_date'         => $expiryDate,
    'note'                => "دفعة ناتجة عن إغلاق أمر الإنتاج رقم #{$order->id}",
]);

            // 4. تحديث الكميات في المستودع الفعلي
            $this->inventoryService->increaseFinishedStock(
                $order->item_id,
                $producedQty
            );

            // 5. تسجيل اللوج الخاص بحالات الإنتاج التقليدي
            $this->logService->log(
                $order, 
                'completed_production', 
                "أكد المسؤول استلام كمية {$producedQty} وإدخالها للمستودع كدفعة جديدة بنجاح."
            );

            // 6. الاحترافية والتتبع: إرسال حركة التوريد إلى سجل حركة المواد العام (ItemTrackingLogs)
            if ($producedQty > 0) {
                $this->trackingService->logProductionReceipt(
                    $batch,            // موديل الدفعة المُنشأة
                    $order,            // موديل أمر الإنتاج
                    $order->item,      // المادة المنتجة
                    $producedQty,      // الكمية المستلمة
                    $warehouseUser     // المستخدم الحالي (أمين المستودع)
                );
            }
            $targetUsers = $this->getUsersByRoles(['warehouse', 'finance','admin']);

            $this->notifyUsers(
                $targetUsers,
                'تم انهاء امر الانتاج',
                "تم انتهاء عملية الانتاج #{$order->id} ",
                'ProductionExecutionService',
                ['order_id' => $order->id,
                'action'=>'complete'
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

//     public function complete($id, $producedQty)
//     {
//         $order = ProductionOrder::findOrFail($id);
// $this->ensureIsProductionOrder($order); // 👈 فحص النوع
//         $remaining =
//             $order->quantity
//             - $order->produced_quantity;

//         if ($producedQty > $remaining) {

//             throw ValidationException::withMessages([
//                 'qty' => 'Exceeded remaining quantity'
//             ]);
//         }

//         $order->increment(
//             'produced_quantity',
//             $producedQty
//         );

//         $this->inventoryService
//             ->increaseFinishedStock(
//                 $order->item_id,
//                 $producedQty
//             );

//         $order->refresh();


//         $order->update([
//             'status' =>
//                 ProductionStatusEnum
//                 ::COMPLETED
//                     ->value,

//         ]);

//         return $order;
//     }
}
