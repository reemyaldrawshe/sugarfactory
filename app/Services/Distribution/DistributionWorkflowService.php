<?php

namespace App\Services\Distribution;

use App\Models\DistributionOrder;
use App\Models\ShipmentItem;
use App\Enums\ProductionStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionWorkflowService
{
    /**
     * 1. موافقة المدير على طلب التوزيع
     */
    public function approveByManager($orderId)
    {
        $order = DistributionOrder::findOrFail($orderId);

        // التحقق من أن الطلب جديد
        if ($order->status !== ProductionStatusEnum::DIST_PENDING->value) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن الموافقة على الطلب، فهو ليس في حالة قيد الانتظار.'
            ]);
        }

        $order->update([
            'status'      => ProductionStatusEnum::DIST_APPROVED->value,
            'approved_at' => now(),
        ]);

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

        return $order;
    }

    /**
     * 3. حجز بضاعة المبيعات من المستودع (تطبيق خوارزمية FEFO)
     */
    public function reserveMaterials($orderId)
    {
        // استخدام Transaction لضمان تراجع النظام عن السحب في حال نقص كمية إحدى المواد
        return DB::transaction(function () use ($orderId) {
            
            // جلب الطلب مع المواد المطلوبة بداخله
            $order = DistributionOrder::with('items.item')->findOrFail($orderId);

            // التحقق من الموافقة
            if ($order->status !== ProductionStatusEnum::DIST_APPROVED->value) {
                throw ValidationException::withMessages([
                    'status' => 'عذراً، يجب أن يحصل الطلب على موافقة الإدارة أولاً لتتمكن من حجز مواده.'
                ]);
            }

            // المرور على كل مادة موجودة في الفاتورة (طلب البيع)
            foreach ($order->items as $orderItem) {
                
                $quantityNeeded = (float) $orderItem->quantity;

                // 💡 جلب الدفعات المتوفرة لهذه المادة وترتيبها من الأقدم للأحدث (FEFO)
                // lockForUpdate(): تمنع أي عملية أخرى من سحب نفس الدفعات حتى تنتهي هذه العملية
                $availableBatches = ShipmentItem::query()
                    ->where('item_id', $orderItem->item_id)
                    ->where('quantity_received', '>', 0)
                    ->orderBy('expiry_date', 'asc') 
                    ->lockForUpdate() 
                    ->get();

                // حساب المجموع الكلي المتوفر للتأكد من قدرتنا على تلبية الطلب
                $totalAvailable = $availableBatches->sum('quantity_received');

                if ($totalAvailable < $quantityNeeded) {
                    throw ValidationException::withMessages([
                        'inventory' => "الكمية المتوفرة في المستودع من مادة ({$orderItem->item->name}) غير كافية. المطلوب: {$quantityNeeded}، المتوفر: {$totalAvailable}"
                    ]);
                }

                // سحب الكمية المطلوبة من الدفعات (قد نسحب من دفعة واحدة أو نتدرج لعدة دفعات)
                foreach ($availableBatches as $batch) {
                    
                    if ($quantityNeeded <= 0) {
                        break; // تم تلبية كمية هذا السطر، ننتقل للسطر/المادة التالية
                    }

                    // نأخذ إما الكمية المتوفرة في الدفعة، أو ما تبقى من احتياجنا (أيهما أقل)
                    $takeQuantity = min($batch->quantity_received, $quantityNeeded);

                    // 1. خصم الكمية من المستودع الفعلي
                    $batch->decrement('quantity_received', $takeQuantity);

                    // 2. تسجيل التوثيق في جدول الحجوزات الوسيط
                    $order->batchAllocations()->create([
                        'distribution_order_item_id' => $orderItem->id,
                        'item_id'                    => $orderItem->item_id,
                        'shipment_item_id'           => $batch->id,
                        'allocated_quantity'         => $takeQuantity,
                    ]);

                    // 3. إنقاص الكمية المتبقية التي نحتاجها
                    $quantityNeeded -= $takeQuantity;
                }
            }

            // تحديث حالة الطلب إلى "تم حجز المواد"
            $order->update([
                'status' => ProductionStatusEnum::DIST_MATERIALS_RESERVED->value,
            ]);

            // إرجاع الطلب مع تفاصيل الدفعات التي تم سحبها
            return $order->load('batchAllocations.shipmentItem', 'items.item');
        });
    }
    /**
     * 4. خروج البضاعة من المستودع للتوصيل (Dispatched)
     * يتم استدعاؤها عندما تغادر الشاحنة باب المستودع
     */
    public function dispatchOrder($orderId)
    {
        $order = DistributionOrder::findOrFail($orderId);

        // يجب أن تكون المواد محجوزة ومجهزة أولاً
        if ($order->status !== ProductionStatusEnum::DIST_MATERIALS_RESERVED->value) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن إرسال البضاعة للتوصيل إلا بعد حجز موادها من المستودع.'
            ]);
        }

        $order->update([
            'status'        => ProductionStatusEnum::DIST_DISPATCHED->value,
            'dispatched_at' => now(),
        ]);

        return $order;
    }

    /**
     * 5. تأكيد استلام العميل للطلبية وإتمام البيع (Sold)
     * يتم استدعاؤها عندما يسلم المندوب البضاعة ويؤكد العميل الاستلام
     */
    public function confirmSale($orderId)
    {
        // نستخدم Transaction هنا تحسباً لإضافة عمليات مالية لاحقاً (مثل توليد قيود محاسبية)
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

            /*
             * 💡 منطقة الربط مع المالية (Financial Integration):
             * هنا بالتحديد، وبمجرد تحول الحالة إلى SOLD، يمكنك مستقبلاً
             * استدعاء خدمة المالية لترحيل الأرباح وإصدار الفاتورة النهائية.
             * مثال توضيحي:
             * FinancialService::generateInvoice($order);
             */

            return $order;
        });
    }
}