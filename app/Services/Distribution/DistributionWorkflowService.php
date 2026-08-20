<?php

namespace App\Services\Distribution;

use App\Models\DistributionOrder;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Enums\ProductionStatusEnum;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DistributionWorkflowService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * 1. موافقة المدير على طلب التوزيع
     */
    public function approveByManager($orderId)
    {
        $order = DistributionOrder::findOrFail($orderId);

        if ($order->status !== ProductionStatusEnum::DIST_PENDING->value) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن الموافقة على الطلب، فهو ليس في حالة قيد الانتظار.'
            ]);
        }

        $order->update([
            'status'      => ProductionStatusEnum::DIST_APPROVED->value,
            'approved_at' => now(),
        ]);

        // 🔔 إشعار لأمين المستودع لوجود طلب صرف مبيعات جديد جاهز للحجز
        $warehouseUsers = $this->getUsersByRoles(['warehouse']);
        $this->notifyUsers(
            $warehouseUsers,
            'طلب صرف مبيعات جديد',
            "وافق المدير على طلب المبيعات رقم #{$order->id} وهو جاهز للحجز والصرف",
            'distributionWorkflow',
            ['distribution_order_id' => $order->id,
             'action'=>'approveByManager'
            ]
        );

        return $order;
    }

    /**
     * 2. رفض المدير للطلب
     */
    public function rejectByManager($orderId)
    {
        $order = DistributionOrder::findOrFail($orderId);

        if ($order->status !== ProductionStatusEnum::DIST_PENDING->value) {
            throw ValidationException::withMessages([
                'status' => 'يمكن رفض الطلبات المعلقة فقط.'
            ]);
        }

        $order->update([
            'status' => ProductionStatusEnum::DIST_REJECTED->value,
        ]);

        // 🔔 إشعار لمُنشئ الطلب (أو قسم المبيعات) برفض الطلب
        $creator = User::find($order->user_id);
        if ($creator) {
            $this->notifyUsers(
                $creator,
                'تم رفض طلب المبيعات',
                "تم رفض طلب المبيعات رقم #{$order->id} من قبل الإدارة",
                'distributionWorkflow',
            ['distribution_order_id' => $order->id,
             'action'=>'rejectByManager'
            ]
            );
        }

        return $order;
    }

    /**
     * 3. حجز بضاعة المبيعات من المستودع (تطبيق خوارزمية FEFO)
     */
    /**
     * 3. حجز المواد الخاصة بطلب المبيعات
     */
    public function reserveMaterials($orderId)
    {
        return DB::transaction(function () use ($orderId) {
            
            $order = DistributionOrder::with('items.item')->findOrFail($orderId);

            if ($order->status !== ProductionStatusEnum::DIST_APPROVED->value) {
                throw ValidationException::withMessages([
                    'status' => 'عذراً، يجب أن يحصل الطلب على موافقة الإدارة أولاً لتتمكن من حجز مواده.'
                ]);
            }

            foreach ($order->items as $orderItem) {
                
                $quantityNeeded = (float) $orderItem->quantity;

                // جلب الدفعات المتاحة للمادة وحساب الصافي (المستلم - المحجوز)
                $availableBatches = ShipmentItem::query()
                    ->where('item_id', $orderItem->item_id)
                    ->whereRaw('(quantity_received - quantity_reserved) > 0')
                    ->orderBy('expiry_date', 'asc') 
                    ->lockForUpdate() 
                    ->get();

                // التأكد من توفر الكميات الصافية المتاحة
                $totalNetAvailable = $availableBatches->sum(function ($b) {
                    return (float) $b->quantity_received - (float) $b->quantity_reserved;
                });

                if ($totalNetAvailable < $quantityNeeded) {
                    throw ValidationException::withMessages([
                        'inventory' => "الكمية المتوفرة في المستودع من مادة ({$orderItem->item->name}) غير كافية. المطلوب: {$quantityNeeded}، المتاح الصافي: {$totalNetAvailable}"
                    ]);
                }

                // سحب وحجز الكميات من الدفعات المتاحة
                foreach ($availableBatches as $batch) {
                    
                    if ($quantityNeeded <= 0) {
                        break;
                    }

                    $batchAvailable = (float) $batch->quantity_received - (float) $batch->quantity_reserved;
                    $takeQuantity = min($batchAvailable, $quantityNeeded);

                    if ($takeQuantity > 0) {
                        // 1. زيادة الكمية المحجوزة في الدفعة
                        $batch->increment('quantity_reserved', $takeQuantity);

                        // 2. إنشاء سجل التخصيص لربطه بالطلب
                        $order->batchAllocations()->create([
                            'distribution_order_item_id' => $orderItem->id,
                            'item_id'                    => $orderItem->item_id,
                            'shipment_item_id'           => $batch->id,
                            'allocated_quantity'         => $takeQuantity,
                        ]);

                        $quantityNeeded -= $takeQuantity;
                    }
                }
            }

            $order->update([
                'status' => ProductionStatusEnum::DIST_MATERIALS_RESERVED->value,
            ]);

            // 🔔 إشعار لقسم المبيعات/مُنشئ الطلب بتجهيز وحجز المواد بالمستودع
            $creator = User::find($order->user_id);
            if ($creator) {
                $this->notifyUsers(
                    $creator,
                    'تم تجهيز وحجز البضاعة',
                    "قام أمين المستودع بحجز وتجهيز المواد لطلب المبيعات رقم #{$order->id}",
                     'distributionWorkflow',
            ['distribution_order_id' => $order->id,
             'action'=>'reserveMaterials'
            ]
                );
            }

            return $order->load('batchAllocations.shipmentItem', 'items.item');
        });
    }

    /**
     * 4. خروج البضاعة من المستودع للتوصيل (Dispatched)
     * (تأكيد الخصم الفعلي وتحرير الحجز)
     */
    public function dispatchOrder($orderId)
    {
        return DB::transaction(function () use ($orderId) {

            $order = DistributionOrder::with('batchAllocations.shipmentItem')->findOrFail($orderId);

            if ($order->status !== ProductionStatusEnum::DIST_MATERIALS_RESERVED->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إرسال البضاعة للتوصيل إلا بعد حجز موادها من المستودع.'
                ]);
            }

            // الخصم الفعلي وتحرير الحجز من الدفعات المحجوزة سابقاً
            foreach ($order->batchAllocations as $allocation) {
                $batch = $allocation->shipmentItem;

                if ($batch) {
                    $batch->decrement('quantity_received', $allocation->allocated_quantity);
                    $batch->decrement('quantity_reserved', $allocation->allocated_quantity);
                }
            }

            $order->update([
                'status'        => ProductionStatusEnum::DIST_DISPATCHED->value,
                'dispatched_at' => now(),
            ]);

            // 🔔 إشعار لقسم المبيعات بأن البضاعة خرجت للتوصيل
            $creator = User::find($order->user_id);
            if ($creator) {
                $this->notifyUsers(
                    $creator,
                    'الطلب في الطريق للتوصيل',
                    "تم شحن وخروج البضاعة لطلب المبيعات رقم #{$order->id} وهي في الطريق للعميل",
                     'distributionWorkflow',
            ['distribution_order_id' => $order->id,
             'action'=>'dispatchOrder'
            ]
                );
            }

            return $order;
        });
    }
    // public function reserveMaterials($orderId)
    // {
    //     return DB::transaction(function () use ($orderId) {
            
    //         $order = DistributionOrder::with('items.item')->findOrFail($orderId);

    //         if ($order->status !== ProductionStatusEnum::DIST_APPROVED->value) {
    //             throw ValidationException::withMessages([
    //                 'status' => 'عذراً، يجب أن يحصل الطلب على موافقة الإدارة أولاً لتتمكن من حجز مواده.'
    //             ]);
    //         }

    //         foreach ($order->items as $orderItem) {
                
    //             $quantityNeeded = (float) $orderItem->quantity;

    //             $availableBatches = ShipmentItem::query()
    //                 ->where('item_id', $orderItem->item_id)
    //                 ->where('quantity_received', '>', 0)
    //                 ->orderBy('expiry_date', 'asc') 
    //                 ->lockForUpdate() 
    //                 ->get();

    //             $totalAvailable = $availableBatches->sum('quantity_received');

    //             if ($totalAvailable < $quantityNeeded) {
    //                 throw ValidationException::withMessages([
    //                     'inventory' => "الكمية المتوفرة في المستودع من مادة ({$orderItem->item->name}) غير كافية. المطلوب: {$quantityNeeded}، المتوفر: {$totalAvailable}"
    //                 ]);
    //             }

    //             foreach ($availableBatches as $batch) {
                    
    //                 if ($quantityNeeded <= 0) {
    //                     break;
    //                 }

    //                 $takeQuantity = min($batch->quantity_received, $quantityNeeded);

    //                 $batch->decrement('quantity_received', $takeQuantity);

    //                 $order->batchAllocations()->create([
    //                     'distribution_order_item_id' => $orderItem->id,
    //                     'item_id'                    => $orderItem->item_id,
    //                     'shipment_item_id'           => $batch->id,
    //                     'allocated_quantity'         => $takeQuantity,
    //                 ]);

    //                 $quantityNeeded -= $takeQuantity;
    //             }
    //         }

    //         $order->update([
    //             'status' => ProductionStatusEnum::DIST_MATERIALS_RESERVED->value,
    //         ]);

    //         // 🔔 إشعار لقسم المبيعات/مُنشئ الطلب بتجهيز وحجز المواد بالمستودع
    //         $creator = User::find($order->user_id);
    //         if ($creator) {
    //             $this->notifyUsers(
    //                 $creator,
    //                 'تم تجهيز وحجز البضاعة',
    //                 "قام أمين المستودع بحجز وتجهيز المواد لطلب المبيعات رقم #{$order->id}",
    //                 'dist_materials_reserved',
    //                 ['distribution_order_id' => $order->id]
    //             );
    //         }

    //         return $order->load('batchAllocations.shipmentItem', 'items.item');
    //     });
    // }

    // /**
    //  * 4. خروج البضاعة من المستودع للتوصيل (Dispatched)
    //  */
    // public function dispatchOrder($orderId)
    // {
    //     $order = DistributionOrder::findOrFail($orderId);

    //     if ($order->status !== ProductionStatusEnum::DIST_MATERIALS_RESERVED->value) {
    //         throw ValidationException::withMessages([
    //             'status' => 'لا يمكن إرسال البضاعة للتوصيل إلا بعد حجز موادها من المستودع.'
    //         ]);
    //     }

    //     $order->update([
    //         'status'        => ProductionStatusEnum::DIST_DISPATCHED->value,
    //         'dispatched_at' => now(),
    //     ]);

    //     // 🔔 إشعار لقسم المبيعات بأن البضاعة خرجت للتوصيل
    //     $creator = User::find($order->user_id);
    //     if ($creator) {
    //         $this->notifyUsers(
    //             $creator,
    //             'الطلب في الطريق للتوصيل',
    //             "تم شحن وخروج البضاعة لطلب المبيعات رقم #{$order->id} وهي في الطريق للعميل",
    //             'dist_dispatched',
    //             ['distribution_order_id' => $order->id]
    //         );
    //     }

    //     return $order;
    // }

    /**
     * 5. تأكيد استلام العميل للطلبية وإتمام البيع (Sold)
     */
    public function confirmSale($orderId)
    {
        return DB::transaction(function () use ($orderId) {
            
            $order = DistributionOrder::findOrFail($orderId);

            if ($order->status !== ProductionStatusEnum::DIST_DISPATCHED->value) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إتمام البيع إلا بعد خروج البضاعة للتوصيل.'
                ]);
            }

            $order->update([
                'status'  => ProductionStatusEnum::DIST_SOLD->value,
                'sold_at' => now(),
            ]);

            // 🔔 إشعار لقسم المالية والمدير بتموم البيع
            $financeAndAdmin = $this->getUsersByRoles(['finance', 'admin']);
            $this->notifyUsers(
                $financeAndAdmin,
                'إتمام عملية بيع',
                "تم تأكيد استلام العميل وإتمام عملية البيع للطلب رقم #{$order->id}",
                 'distributionWorkflow',
            ['distribution_order_id' => $order->id,
             'action'=>'confirmSale'
            ]
            );

            return $order;
        });
    }

    /**
     * 🔍 جلب المستخدمين حسب الأدوار
     */
    private function getUsersByRoles(array $roles)
    {
        if (method_exists(User::class, 'scopeRole')) {
            return User::role($roles)->get();
        }

        return User::whereIn('role', $roles)->get();
    }

    /**
     * 📩 إرسال الإشعار وحفظه عبر NotificationService
     */
    private function notifyUsers($users, string $title, string $message, string $type = 'distribution', array $extraData = [])
    {
        if (!$users || (is_countable($users) && count($users) === 0)) {
            return;
        }

        if ($users instanceof User) {
            $users = collect([$users]);
        }

        foreach ($users as $user) {
            try {
                $this->notificationService->send($user, $title, $message, $type, $extraData);
            } catch (\Throwable $e) {
                Log::error("Failed to send notification to user ID {$user->id}: " . $e->getMessage());
            }
        }
    }
}