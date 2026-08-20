<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use App\Models\ShipmentItem;
use App\Models\ItemTrackingLog;
use App\Models\DistributionOrder;
use App\Models\DistributionOrderItem;
use App\Models\DistributionOrderMaterial;
use App\Enums\ProductionStatusEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DistributionOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. استرجاع المنتج النهائي (سكر أبيض) والمستخدم المسؤول
        $sugarItem = Item::where('name', 'سكر أبيض')->first();
        $user = User::first();

        if (!$sugarItem || !$user) {
            $this->command->warn('تنبيه: لم يتم العثور على مادة "سكر أبيض" أو مستخدم لتشغيل Seed المبيعات.');
            return;
        }

        // جلب دفعات السكر الأبيض المتوفرة مرتبة حسب الأقدمية (FEFO/FIFO)
        $availableBatches = ShipmentItem::where('item_id', $sugarItem->id)
            ->where('quantity_received', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();

        if ($availableBatches->isEmpty()) {
            $this->command->warn('تنبيه: لا توجد دفعات سكر أبيض متوفرة بجدول shipment_items لتخصيص المبيعات.');
            return;
        }

        // 2. محاكاة سيناريوهات طلبات البيع والتوزيع لمصنع السكر
        $orderScenarios = [
            [
                'status'        => 'pending',
                'customer'      => 'شركة الشرق للحلويات والمخبوزات',
                'quantity'      => 15.000, // 15 طن
                'price'         => 1250000,
                'approved_at'   => null,
                'dispatched_at' => null,
                'sold_at'       => null,
                'notes'         => 'طلب مبيعات جديد بانتظار الاعتماد المالي والائتماني.',
            ],
            [
                'status'        => 'approved',
                'customer'      => 'معمل الفرات للشراب والمشروبات الغازية',
                'quantity'      => 25.000, // 25 طن
                'price'         => 1240000,
                'approved_at'   => Carbon::now()->subHours(6),
                'dispatched_at' => null,
                'sold_at'       => null,
                'notes'         => 'تمت موافقة إدارة المبيعات ومحال لمستودع المنتج النهائي للحجز.',
            ],
            [
                'status'        => 'allocated',
                'customer'      => 'مؤسسة التجارة بالجملة - دمشق',
                'quantity'      => 50.000, // 50 طن (تتطلب تخصيص من أكثر من دفعة)
                'price'         => 1230000,
                'approved_at'   => Carbon::now()->subDays(1),
                'dispatched_at' => null,
                'sold_at'       => null,
                'notes'         => 'تم حجز الكمية المطلوب تجهيزها وتحميلها من الجملون رقم 2.',
            ],
            [
                'status'        => 'dispatched',
                'customer'      => 'المؤسسة السورية للتجارة (عقد حكومي)',
                'quantity'      => 100.000, // 100 طن
                'price'         => 1200000,
                'approved_at'   => Carbon::now()->subDays(2),
                'dispatched_at' => Carbon::now()->subHours(8),
                'sold_at'       => null,
                'notes'         => 'تم خروج أربع شاحنات من المعمل وقيد التوصيل للمستودعات المركزية.',
            ],
            [
                'status'        => 'sold',
                'customer'      => 'شركة الكانسروة والأغذية المحفوظة',
                'quantity'      => 30.000, // 30 طن
                'price'         => 1260000,
                'approved_at'   => Carbon::now()->subDays(5),
                'dispatched_at' => Carbon::now()->subDays(4),
                'sold_at'       => Carbon::now()->subDays(2),
                'notes'         => 'تم التسليم بالكامل وتسوية الدفعة المالية وإغلاق الطلب.',
            ],
        ];

        // 3. إنشاء طلبات التوزيع وتوزيع الدفعات وتوليد حركة التتبع
        $batchIndex = 0;

        foreach ($orderScenarios as $scenario) {
            
            // أ. إنشاء أمر التوزيع الرئيسي
            $order = DistributionOrder::create([
                'user_id'       => $user->id,
                'customer_name' => $scenario['customer'],
                'status'        => $scenario['status'],
                'notes'         => $scenario['notes'],
                'approved_at'   => $scenario['approved_at'],
                'dispatched_at' => $scenario['dispatched_at'],
                'sold_at'       => $scenario['sold_at'],
            ]);

            // ب. إضافة بند المنتج في أمر التوزيع
            $totalPrice = $scenario['quantity'] * $scenario['price'];

            $orderItem = DistributionOrderItem::create([
                'distribution_order_id' => $order->id,
                'item_id'               => $sugarItem->id,
                'quantity'              => $scenario['quantity'],
                'price_per_ton'         => $scenario['price'],
                'total_price'           => $totalPrice,
            ]);

            // ج. تخصيص الدفعات (FEFO Allocation) للحالات التي تجاوزت مرحلة الموافقة
            $needsAllocation = in_array($scenario['status'], ['allocated', 'dispatched', 'sold']);

            if ($needsAllocation) {
                $remainingToAllocate = $scenario['quantity'];

                while ($remainingToAllocate > 0 && isset($availableBatches[$batchIndex])) {
                    $currentBatch = $availableBatches[$batchIndex];
                    
                    // حساب الكمية المأخوذة من الدفعة الحالية
                    $allocatedFromBatch = min($remainingToAllocate, $currentBatch->quantity_received);

                    DistributionOrderMaterial::create([
                        'distribution_order_id'      => $order->id,
                        'distribution_order_item_id' => $orderItem->id,
                        'item_id'                    => $sugarItem->id,
                        'shipment_item_id'           => $currentBatch->id,
                        'allocated_quantity'         => $allocatedFromBatch,
                    ]);

                    $remainingToAllocate -= $allocatedFromBatch;

                    // الانتقال للدفعة التالية إذا استُنفدت هذه الدفعة في التخصيص
                    if ($allocatedFromBatch >= $currentBatch->quantity_received) {
                        $batchIndex = ($batchIndex + 1) % $availableBatches->count();
                    }
                }

                // د. إضافة سجلات التتبع عند الخروج الفعلي للبضاعة من المعمل
                if (in_array($scenario['status'], ['dispatched', 'sold'])) {
                    ItemTrackingLog::create([
                        'type'                => 'بيع وتوزيع',
                        'trackable_id'        => $order->id,
                        'trackable_type'      => DistributionOrder::class,
                        'status'              => $scenario['status'],
                        'item_id'             => $sugarItem->id,
                        'item_name'           => $sugarItem->name,
                        'quantity'            => $scenario['quantity'],
                        'shipment_id'         => $availableBatches[0]->id,
                        'sent_from_role'      => 'warehouse',
                        'sent_from_user_name' => 'أمين مستودع المنتج النهائي',
                        'sent_from_user_id'   => $user->id,
                        'sent_to_role'        => 'sales',
                        'sent_to_user_name'   => $scenario['customer'],
                        'sent_to_user_id'     => $user->id,
                        'notes'               => "صرف بضاعة جاهزة بموجب أمر التوزيع رقم #{$order->id}",
                    ]);
                }
            }
        }
    }
}
// class DistributionOrderSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         // 1. تجهيز البيانات الأساسية من قاعدة البيانات
//         $finalItem = Item::first(); // المنتج النهائي (مثل السكر المعبأ)
//         $user = User::first(); // الموظف المنشئ للطلب
//         $batch = ShipmentItem::where('item_id', $finalItem?->id)->first(); // الدفعة المتوفرة بالمستودع

//         if (!$finalItem || !$user) {
//             $this->command->warn('تنبيه: يجب أن يحتوي جدول الأسمال (items) والمستخدمين (users) على بيانات أولاً قبل تشغيل هذا السيدر.');
//             return;
//         }

//         // 2. مصفوفة حالات الطلبات لمحاكاة دورة الحياة الكاملة (Workflow)
//         // ملاحظة: استبدل القيم النصية هنا بحالات الـ Enum الفعلية لديك إذا كانت مختلفة
//         $orderScenarios = [
//             [
//                 'status' => 'pending', // بانتظار الموافقة
//                 'customer' => 'شركة الفرات للتجارة',
//                 'quantity' => 15.500, // 15.5 طن مثلاً
//                 'price' => 1250000,
//                 'approved_at' => null,
//                 'dispatched_at' => null,
//                 'sold_at' => null,
//                 'notes' => 'طلب مبيعات جديد مضاف وبانتظار تدقيق الإدارة المالية.',
//             ],
//             [
//                 'status' => 'approved', // تمت الموافقة ولكن لم يتم تخصيص دفعات المستودع بعد
//                 'customer' => 'مستودعات النور والبركة',
//                 'quantity' => 20.000,
//                 'price' => 1240000,
//                 'approved_at' => Carbon::now()->subHours(5),
//                 'dispatched_at' => null,
//                 'sold_at' => null,
//                 'notes' => 'تمت موافقة الإدارة ومحال لأمين المستودع للتخصيص الصلاحي.',
//             ],
//             [
//                 'status' => 'allocated', // تمت الموافقة وتخصيص الدفعات بناء على الـ FEFO
//                 'customer' => 'تاجر جملة - دمشق',
//                 'quantity' => 30.000,
//                 'price' => 1250000,
//                 'approved_at' => Carbon::now()->subDays(1),
//                 'dispatched_at' => null,
//                 'sold_at' => null,
//                 'notes' => 'تم حجز الكميات من الدفعات الأقدم صلاحية بنجاح وهي بانتظار التحميل.',
//             ],
//             [
//                 'status' => 'dispatched', // خرجت الشحنة من المعمل وقيد التوصيل
//                 'customer' => 'المؤسسة السورية للتجارة',
//                 'quantity' => 50.000,
//                 'price' => 1200000,
//                 'approved_at' => Carbon::now()->subDays(2),
//                 'dispatched_at' => Carbon::now()->subHours(12),
//                 'sold_at' => null,
//                 'notes' => 'الشحنة خرجت على متن الشاحنة رقم (أ-54321) وقيد التوصيل.',
//             ],
//             [
//                 'status' => 'sold', // اكتملت العملية وتم البيع والقبض النهائي
//                 'customer' => 'شركة الخارطة للمواد الغذائية',
//                 'quantity' => 10.000,
//                 'price' => 1260000,
//                 'approved_at' => Carbon::now()->subDays(4),
//                 'dispatched_at' => Carbon::now()->subDays(3),
//                 'sold_at' => Carbon::now()->subDays(2),
//                 'notes' => 'تم استلام الشحنة من قبل العميل وتثبيت الدفعة المالية بالكامل.',
//             ],
//         ];

//         // 3. التكرار لإنشاء البيانات وتوزيعها على الجداول الثلاثة
//         foreach ($orderScenarios as $scenario) {
            
//             // أ. إدخال السجل في جدول رأس الطلب (distribution_orders)
//             $order = DistributionOrder::create([
//                 'user_id'       => $user->id,
//                 'customer_name' => $scenario['customer'],
//                 'status'        => $scenario['status'], // أو استخدم الـ Enum مثل: ProductionStatusEnum::DIST_PENDING->value
//                 'notes'         => $scenario['notes'],
//                 'approved_at'   => $scenario['approved_at'],
//                 'dispatched_at' => $scenario['dispatched_at'],
//                 'sold_at'       => $scenario['sold_at'],
//             ]);

//             // ب. إدخال السجل في جدول أسطر المواد (distribution_order_items)
//             $totalPrice = $scenario['quantity'] * $scenario['price'];
            
//             $orderItem = DistributionOrderItem::create([
//                 'distribution_order_id' => $order->id,
//                 'item_id'               => $finalItem->id,
//                 'quantity'              => $scenario['quantity'],
//                 'price_per_ton'         => $scenario['price'],
//                 'total_price'           => $totalPrice,
//             ]);

//             // ج. إدخال السجل في جدول تخصيص الدفعات (distribution_order_materials)
//             // نربط الدفعة فقط للحالات التي تجاوزت مرحلة الموافقة (أي تمت جدولة أو شحن أو إتمام البضاعة)
//             $needsAllocation = in_array($scenario['status'], ['allocated', 'dispatched', 'sold']);

//             if ($needsAllocation && $batch) {
//                 DistributionOrderMaterial::create([
//                     'distribution_order_id'      => $order->id,
//                     'distribution_order_item_id' => $orderItem->id,
//                     'item_id'                    => $finalItem->id,
//                     'shipment_item_id'           => $batch->id, // الدفعة المخزنية المحددة FEFO
//                     'allocated_quantity'         => $scenario['quantity'],
//                 ]);

//                 // د. إضافة سجل تتبع حركة المخزون (Item Tracking Log) للحالات التي خرجت فعلياً من المستودع
//                 if (in_array($scenario['status'], ['dispatched', 'sold'])) {
//                     ItemTrackingLog::create([
//                         'type'                 => 'بيع وتوزيع',
//                         'trackable_id'         => $order->id,
//                         'trackable_type'       => DistributionOrder::class,
//                         'status'               => $scenario['status'],
//                         'item_id'              => $finalItem->id,
//                         'item_name'            => $finalItem->name,
//                         'quantity'             => $scenario['quantity'],
//                         'shipment_id'          => $batch->id,
//                         'sent_from_role'       => 'warehouse',
//                         'sent_from_user_name'  => 'أمين مستودع الإنتاج الجاهز',
//                         'sent_from_user_id'    => $user->id,
//                         'sent_to_role'         => 'sales',
//                         'sent_to_user_name'    => $scenario['customer'],
//                         'sent_to_user_id'      => $user->id,
//                         'notes'                => "حركة صرف مخزني بموجب أمر التوزيع رقم #{$order->id}",
//                     ]);
//                 }
//             }
//         }
//     }
// }