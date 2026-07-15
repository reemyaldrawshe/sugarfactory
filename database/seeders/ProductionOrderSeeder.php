<?php

namespace Database\Seeders;

use App\Models\BOM;
use App\Models\Item;
use App\Models\ItemTrackingLog;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | تجهيز البيانات الأساسية
        |--------------------------------------------------------------------------
        */
        $finalItem = Item::first();
        $warehouseUser = User::first();

        /*
        |--------------------------------------------------------------------------
        | محاكاة الطلبات (تشمل الآن قسم المبيعات والإدارة)
        |--------------------------------------------------------------------------
        */
        $orders = [
            // 1. طلبات قسم المبيعات
            [
                'quantity' => 100,
                'produced_quantity' => 0,
                'status' => 'pending', 
                'notes' => 'طلب جديد من المبيعات بانتظار موافقة الإدارة',
            ],
            [
                'quantity' => 30,
                'produced_quantity' => 0,
                // التعديل هنا: استخدام القيمة المطابقة للـ Enum تماماً
                'status' => 'rejected_by_manager', 
                'notes' => 'طلب مبيعات تم رفضه من قبل الإدارة',
            ],
            
            // 2. طلبات الإدارة والمستودع
            [
                'quantity' => 50,
                'produced_quantity' => 0,
                'status' => 'approved_by_manager',
                'notes' => 'موافق عليه، بانتظار تحضير أمين المستودع',
            ],
            [
                'quantity' => 60,
                'produced_quantity' => 0,
                'status' => 'materials_reserved',
                'notes' => 'تم حجز المواد من المستودع',
            ],

            // 3. طلبات قسم الإنتاج
            [
                'quantity' => 150,
                'produced_quantity' => 20, 
                'status' => 'sent_to_production',
                'notes' => 'الطلب وصل لقسم الإنتاج (إنتاج جزئي)',
            ],
            [
                'quantity' => 80,
                'produced_quantity' => 45, 
                'status' => 'in_production',
                'notes' => 'العمل جارٍ في صالة الإنتاج',
            ],
            [
                'quantity' => 70,
                'produced_quantity' => 30, 
                'status' => 'paused',
                'notes' => 'الإنتاج متوقف مؤقتاً لمشكلة تقنية',
            ],
            [
                'quantity' => 200,
                'produced_quantity' => 200, 
                'status' => 'completed',
                'notes' => 'تم الإنتاج بالكامل وتسليم الكميات',
            ],
        ];
        // $orders = [
        //     // 1. طلبات قسم المبيعات
        //     [
        //         'quantity' => 100,
        //         'produced_quantity' => 0,
        //         'status' => 'pending', 
        //         'notes' => 'طلب جديد من المبيعات بانتظار موافقة الإدارة',
        //     ],
        //     [
        //         'quantity' => 30,
        //         'produced_quantity' => 0,
        //         'status' => 'rejected', 
        //         'notes' => 'طلب مبيعات تم رفضه من قبل الإدارة',
        //     ],
            
        //     // 2. طلبات الإدارة والمستودع
        //     [
        //         'quantity' => 50,
        //         'produced_quantity' => 0,
        //         'status' => 'approved_by_manager',
        //         'notes' => 'موافق عليه، بانتظار تحضير أمين المستودع',
        //     ],
        //     [
        //         'quantity' => 60,
        //         'produced_quantity' => 0,
        //         'status' => 'materials_reserved',
        //         'notes' => 'تم حجز المواد من المستودع',
        //     ],

        //     // 3. طلبات قسم الإنتاج
        //     [
        //         'quantity' => 150,
        //         'produced_quantity' => 20, 
        //         'status' => 'sent_to_production',
        //         'notes' => 'الطلب وصل لقسم الإنتاج (إنتاج جزئي)',
        //     ],
        //     [
        //         'quantity' => 80,
        //         'produced_quantity' => 45, 
        //         'status' => 'in_production',
        //         'notes' => 'العمل جارٍ في صالة الإنتاج',
        //     ],
        //     [
        //         'quantity' => 70,
        //         'produced_quantity' => 30, 
        //         'status' => 'paused',
        //         'notes' => 'الإنتاج متوقف مؤقتاً لمشكلة تقنية',
        //     ],
        //     [
        //         'quantity' => 200,
        //         'produced_quantity' => 200, 
        //         'status' => 'completed',
        //         'notes' => 'تم الإنتاج بالكامل وتسليم الكميات',
        //     ],
        // ];

        foreach ($orders as $data) {
            
            // إنشاء الطلب
            $order = ProductionOrder::create([
                'item_id' => $finalItem->id,
                'quantity' => $data['quantity'],
                'produced_quantity' => $data['produced_quantity'],
                'status' => $data['status'],
                'notes' => $data['notes'],
                'warehouse_id' => 2, // تأكد أن هذا الـ ID موجود فعلياً في جدول المستودعات
                'production_id' => 6, // تأكد أن هذا الـ ID موجود في جدول جهات الإنتاج
            ]);

            /*
            |--------------------------------------------------------------------------
            | حجز المواد (فقط للطلبات التي تجاوزت الإدارة وبدأ العمل عليها)
            |--------------------------------------------------------------------------
            */
            $isPreparedOrBeyond = in_array($data['status'], [
                'materials_reserved',
                'sent_to_production',
                'in_production',
                'paused',
                'completed'
            ]);

            if ($isPreparedOrBeyond) {

                $boms = BOM::where('final_item_id', $finalItem->id)->get();

                foreach ($boms as $bom) {
                    
                    $required = $bom->basic_item_quantity * $order->quantity;
                    $batch = ShipmentItem::where('item_id', $bom->basic_item_id)->first();

                    if (!$batch) {
                        continue;
                    }

                    ProductionOrderMaterial::create([
                        'production_order_id' => $order->id,
                        'item_id' => $bom->basic_item_id,
                        'shipment_item_id' => $batch->id,
                        'required_quantity' => $required,
                        'consumed_quantity' => min($required, 100),
                    ]);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | إضافة سجلات التتبع (Tracking Logs) للطلبات المرسلة للإنتاج
        |--------------------------------------------------------------------------
        */
        $ordersWithMaterials = ProductionOrder::with(['materials.item'])->get();

        foreach ($ordersWithMaterials as $order) {

            // تم تصحيح مسميات الحالات هنا لتتوافق مع الـ Enums الفعلية التي نستخدمها
            $reachedProduction = in_array($order->status, [
                'sent_to_production',
                'in_production',
                'paused',
                'completed'
            ]);

            if (!$reachedProduction) {
                continue;
            }

            foreach ($order->materials as $material) {

                ItemTrackingLog::create([
                    'type' => 'صرف',
                    'trackable_id' => $order->id,
                    'trackable_type' => ProductionOrder::class,
                    'status' => $order->status,
                    'item_id' => $material->item_id,
                    'item_name' => $material->item->name ?? 'مادة غير معروفة',
                    'quantity' => $material->consumed_quantity,
                    'shipment_id' => null,
                    'sent_from_role' => 'warehouse',
                    'sent_from_user_name' => $warehouseUser->name ?? 'أمين المستودع',
                    'sent_from_user_id' => $warehouseUser->id ?? 1,
                    'sent_to_role' => 'production',
                    'sent_to_user_name' => 'Production Department',
                    'sent_to_user_id' => 0,
                    'notes' => "Materials issued for production order #{$order->id}",
                ]);
            }
        }
    }
}