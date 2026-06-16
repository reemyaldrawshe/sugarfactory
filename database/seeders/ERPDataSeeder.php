<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

// استيراد كافة الموديلات (لحل خطأ الـ IDE وتسهيل التعامل)
use App\Models\User;
use App\Models\Section;
use App\Models\Unit;
use App\Models\Item;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\BOM;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\DemolishOrder;
use App\Models\ItemTrackingLog;
use App\Models\ShipmentStatusHistory;
use App\Models\ProductionOrderLog;

class ERPDataSeeder extends Seeder
{
    public function run(): void
    {
        // استخدام Transaction لضمان سرعة الإدخال وسلامة البيانات
        DB::transaction(function () {

            $faker = Faker::create('ar_SA');

            // // 1. المستخدمون
             $roles = ['admin', 'warehouse', 'production', 'tester'];
            // for ($i = 0; $i < 20; $i++) {
            //     $user = User::create([
            //         'name' => $faker->name,
            //         'email' => $faker->unique()->safeEmail,
            //         'gender' => $faker->randomElement(['male', 'female']),
            //         'password' => bcrypt('password'),
            //         'lang' => 'ar'
            //     ]);
            //     $user->assignRole($faker->randomElement($roles));
            // }
             $users = User::all();

            // 2. المواد
            $units = Unit::all()->pluck('id');
            $sections = Section::all();
            $allItems = Item::all();
            $rawItems = $allItems->where('is_raw_material', true);
            $finishedItems = $allItems->where('is_raw_material', false);

            // 3. الشحنات وتفاصيلها + Logs (تعديل الحالات هنا)
            $statuses = [
                'pending_admin',
                'pending_purchase',
                'ready_at_warehouse',
                'pending_lab',
                'approved_lab',
                'rejected_lab',
                'finished'
            ];

            foreach ($statuses as $status) {
                for ($i = 0; $i < 5; $i++) { // توليد 5 شحنات لكل حالة
                    $shipment = Shipment::create([
                        'supplier' => $faker->company,
                        'received_at' => $faker->dateTimeThisYear,
                        'status' => $status, // إسناد الحالة الديناميكية هنا
                        'warehouse_id' => $users->random()->id,
                        'notes' => $faker->sentence,
                    ]);

                    foreach ($rawItems->random(3) as $item) {
                        $shipItem = ShipmentItem::create([
                            'shipment_id' => $shipment->id,
                            'item_id' => $item->id,
                            'quantity_required' => 1000,
                            'quantity_received' => rand(500, 2000),
                            'price' => rand(50, 500),
                            'expiry_date' => $faker->dateTimeBetween('now', '+1 year'),
                        ]);

                        ItemTrackingLog::create([
                            'type' => 'توريد',
                            'trackable_id' => $shipItem->id,
                            'trackable_type' => 'ShipmentItem',
                            'status' => 'received',
                            'item_id' => $item->id,
                            'item_name' => $item->name,
                            'quantity' => $shipItem->quantity_received,
                            'sent_from_role' => 'supplier',
                            'sent_from_user_name' => 'Supplier',
                            'sent_from_user_id' => 1,
                            'sent_to_role' => 'warehouse',
                            'sent_to_user_name' => 'Warehouse Admin',
                            'sent_to_user_id' => $users->random()->id,
                        ]);
                    }

                    // إضافة سجل الحالة للشحنة بالاعتماد على الحالة الحالية
                    ShipmentStatusHistory::create([
                        'shipment_id' => $shipment->id,
                        'old_status' => 'pending',
                        'new_status' => $status, // إسناد الحالة الديناميكية هنا
                        'changed_by' => $users->random()->id,
                        'reason' => 'SEEDER_INITIAL_DATA',
                    ]);
                }
            }

            // 4. الـ BOM
            foreach ($finishedItems as $fItem) {
                foreach ($rawItems->random(3) as $rItem) {
                    BOM::create([
                        'final_item_id' => $fItem->id,
                        'basic_item_id' => $rItem->id,
                        'basic_item_quantity' => rand(1, 10),
                        'final_item_quantity' => 1,
                    ]);
                }
            }

            // 5. أوامر الإنتاج + Logs
            foreach ($finishedItems as $fItem) {
                $order = ProductionOrder::create([
                    'item_id' => $fItem->id,
                    'quantity' => 100,
                    'produced_quantity' => 80,
                    'status' => 'completed',
                    'notes' => 'إنتاج آلي من الـ Seeder',
                    'warehouse_id' => 2,
                    'production_id' => 6,
                ]);

                ProductionOrderLog::create([
                    'production_order_id' => $order->id,
                    'user_id' => $users->random()->id,
                    'action' => 'completed',
                    'notes' => 'تم إغلاق الأمر بنجاح',
                ]);

                $boms = BOM::where('final_item_id', $fItem->id)->get();
                foreach ($boms as $bom) {
                    $shipItem = ShipmentItem::where('item_id', $bom->basic_item_id)->first();
                    if ($shipItem) {
                        ProductionOrderMaterial::create([
                            'production_order_id' => $order->id,
                            'item_id' => $bom->basic_item_id,
                            'shipment_item_id' => $shipItem->id,
                            'required_quantity' => 50,
                            'consumed_quantity' => 45,
                        ]);
                    }
                }
            }

            // 6. محاضر الإتلاف
            for ($i = 0; $i < 20; $i++) {
                DemolishOrder::create([
                    'section_id' => $sections->random()->id,
                    'item_id' => $allItems->random()->id,
                    'quantity' => rand(1, 50),
                    'reason' => $faker->sentence,
                    'status' => 'completed',
                    'created_by' => $users->random()->id,
                ]);
            }
        });
    }
}
