<?php


namespace Database\Seeders;

use App\Models\Item;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | مستخدم مستودع
        |--------------------------------------------------------------------------
        */
        $warehouseUser = User::first();

        /*
        |--------------------------------------------------------------------------
        | 1. إنشاء شحنات ودفعات خاصة بمادة "سكر أبيض"
        | الهدف: أكثر من 1000 طن، موزعة 25 طن لكل دفعة بتواريخ انتهاء مختلفة
        |--------------------------------------------------------------------------
        */
        $sugarItem = Item::where('name', 'سكر أبيض')->first();

        if ($sugarItem) {
            // 42 دفعة * 25 طن = 1050 طن (تجاوزنا الـ 1000 طن المطلوبة)
            $totalBatches = 42; 

            for ($i = 1; $i <= $totalBatches; $i++) {
                
                // إنشاء شحنة لكل دفعة لضمان استقلالية التواريخ والبيانات
                $shipment = Shipment::create([
                    'supplier' => 'شركة السكر الوطنية الموردة',
                    'received_at' => now()->subDays(rand(1, 10)),
                    'status' => 'approved_lab',
                    'warehouse_id' => $warehouseUser->id,
                    'admin_approved_by' => $warehouseUser->id,
                    'admin_approved_at' => now(),
                    'warehouse_confirmed_by' => $warehouseUser->id,
                    'warehouse_confirmed_at' => now(),
                    'final_confirmed_by' => $warehouseUser->id,
                    'final_confirmed_at' => now(),
                    'notes' => 'شحنة سكر أبيض رقم ' . $i,
                ]);

                // إضافة دفعة السكر (25 طن)
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'item_id' => $sugarItem->id,
                    'quantity_required' => 25,
                    'quantity_received' => 25,
                    'price' => rand(1000, 1100), // سعر التكلفة (أقل من سعر المبيع 1200)
                    
                    // 💡 تاريخ انتهاء مختلف لكل دفعة (نزيد شهر مع كل لفة بالـ loop)
                    'expiry_date' => now()->addMonths($i + 6)->format('Y-m-d'),
                    
                    'invoice_image' => null,
                    'lab_test_file' => null,
                    'note' => "دفعة سكر أبيض رقم {$i} بوزن 25 طن",
                    'price_history' => json_encode([
                        [
                            'price' => 1100,
                            'date' => now()->toDateTimeString(),
                        ]
                    ]),
                    'quantity_history' => json_encode([
                        [
                            'quantity' => 25,
                            'date' => now()->toDateTimeString(),
                        ]
                    ]),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. إنشاء شحنة تجريبية واحدة لباقي المواد (المواد الخام)
        |--------------------------------------------------------------------------
        */
        $otherItems = Item::where('name', '!=', 'سكر أبيض')->get();

        if ($otherItems->count() > 0) {
            $shipment = Shipment::create([
                'supplier' => 'مورد المواد الخام',
                'received_at' => now()->subDays(2),
                'status' => 'approved_lab',
                'warehouse_id' => $warehouseUser->id,
                'admin_approved_by' => $warehouseUser->id,
                'admin_approved_at' => now(),
                'warehouse_confirmed_by' => $warehouseUser->id,
                'warehouse_confirmed_at' => now(),
                'final_confirmed_by' => $warehouseUser->id,
                'final_confirmed_at' => now(),
                'notes' => 'شحنة مواد خام أولية',
            ]);

            foreach ($otherItems as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'item_id' => $item->id,
                    'quantity_required' => 5000, // كمية كبيرة من المواد الخام
                    'quantity_received' => 5000,
                    'price' => rand(10, 100),
                    'expiry_date' => now()->addMonths(rand(12, 24))->format('Y-m-d'),
                    'invoice_image' => null,
                    'lab_test_file' => null,
                    'note' => 'دفعة للمادة الخام: ' . $item->name,
                    'price_history' => json_encode([
                        ['price' => 50, 'date' => now()->toDateTimeString()]
                    ]),
                    'quantity_history' => json_encode([
                        ['quantity' => 5000, 'date' => now()->toDateTimeString()]
                    ]),
                ]);
            }
        }
    }
}

// namespace Database\Seeders;

// use App\Models\Item;
// use App\Models\Shipment;
// use App\Models\ShipmentItem;
// use App\Models\User;
// use Illuminate\Database\Seeder;

// class ShipmentSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {

//         /*
//         |--------------------------------------------------------------------------
//         | مستخدم مستودع
//         |--------------------------------------------------------------------------
//         */
//         $warehouseUser = User::first();

//         /*
//         |--------------------------------------------------------------------------
//         | إنشاء شحنات
//         |--------------------------------------------------------------------------
//         */
//         for ($i = 1; $i <= 3; $i++) {

//             $shipment = Shipment::create([

//                 'supplier' => 'Supplier ' . $i,

//                 'received_at' => now()->subDays($i),

//                 'status' => 'approved_lab',

//                 'warehouse_id' => $warehouseUser->id,

//                 'admin_approved_by' => $warehouseUser->id,
//                 'admin_approved_at' => now(),

//                 'warehouse_confirmed_by' => $warehouseUser->id,
//                 'warehouse_confirmed_at' => now(),

//                 'final_confirmed_by' => $warehouseUser->id,
//                 'final_confirmed_at' => now(),

//                 'notes' => 'شحنة تجريبية رقم ' . $i,
//             ]);

//             /*
//             |--------------------------------------------------------------------------
//             | عناصر الشحنة
//             |--------------------------------------------------------------------------
//             */
//             $items = Item::all();

//             foreach ($items as $item) {

//                 ShipmentItem::create([

//                     'shipment_id' => $shipment->id,

//                     'item_id' => $item->id,

//                     'quantity_required' => 500,

//                     'quantity_received' => rand(3000, 5000),

//                     'price' => rand(10, 100),

//                     'expiry_date' => now()
//                         ->addMonths(rand(3, 12))
//                         ->format('Y-m-d'),

//                     'invoice_image' => null,

//                     'lab_test_file' => null,

//                     'note' => 'دفعة للمادة ' . $item->name,

//                     'price_history' => json_encode([
//                         [
//                             'price' => rand(10, 100),
//                             'date' => now(),
//                         ]
//                     ]),

//                     'quantity_history' => json_encode([
//                         [
//                             'quantity' => rand(300, 500),
//                             'date' => now(),
//                         ]
//                     ]),
//                 ]);
//             }
//         }
//     }
// }