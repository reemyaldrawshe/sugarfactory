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
            'dist_approved',
            ['distribution_order_id' => $order->id]
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
                'dist_rejected',
                ['distribution_order_id' => $order->id]
            );
        }

        return $order;
    }

    /**
     * 3. حجز بضاعة المبيعات من المستودع (تطبيق خوارزمية FEFO)
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

                $availableBatches = ShipmentItem::query()
                    ->where('item_id', $orderItem->item_id)
                    ->where('quantity_received', '>', 0)
                    ->orderBy('expiry_date', 'asc') 
                    ->lockForUpdate() 
                    ->get();

                $totalAvailable = $availableBatches->sum('quantity_received');

                if ($totalAvailable < $quantityNeeded) {
                    throw ValidationException::withMessages([
                        'inventory' => "الكمية المتوفرة في المستودع من مادة ({$orderItem->item->name}) غير كافية. المطلوب: {$quantityNeeded}، المتوفر: {$totalAvailable}"
                    ]);
                }

                foreach ($availableBatches as $batch) {
                    
                    if ($quantityNeeded <= 0) {
                        break;
                    }

                    $takeQuantity = min($batch->quantity_received, $quantityNeeded);

                    $batch->decrement('quantity_received', $takeQuantity);

                    $order->batchAllocations()->create([
                        'distribution_order_item_id' => $orderItem->id,
                        'item_id'                    => $orderItem->item_id,
                        'shipment_item_id'           => $batch->id,
                        'allocated_quantity'         => $takeQuantity,
                    ]);

                    $quantityNeeded -= $takeQuantity;
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
                    'dist_materials_reserved',
                    ['distribution_order_id' => $order->id]
                );
            }

            return $order->load('batchAllocations.shipmentItem', 'items.item');
        });
    }

    /**
     * 4. خروج البضاعة من المستودع للتوصيل (Dispatched)
     */
    public function dispatchOrder($orderId)
    {
        $order = DistributionOrder::findOrFail($orderId);

        if ($order->status !== ProductionStatusEnum::DIST_MATERIALS_RESERVED->value) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن إرسال البضاعة للتوصيل إلا بعد حجز موادها من المستودع.'
            ]);
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
                'dist_dispatched',
                ['distribution_order_id' => $order->id]
            );
        }

        return $order;
    }

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
                'dist_sold',
                ['distribution_order_id' => $order->id]
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