<?php


namespace Database\Seeders;

use App\Models\Item;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseUser = User::first();
        $userId = $warehouseUser->id ?? 1;

        /*
        |--------------------------------------------------------------------------
        | 1. شحنات منتج نهائي (سكر أبيض) - 42 دفعة × 25 طن = 1050 طن
        |--------------------------------------------------------------------------
        */
        $sugarItem = Item::where('name', 'سكر أبيض')->first();

        if ($sugarItem) {
            $totalSugarBatches = 42;

            for ($i = 1; $i <= $totalSugarBatches; $i++) {
                $price = rand(780, 810);
                $quantity = 25;

                $shipment = Shipment::create([
                    'supplier'               => 'إنتاج داخلي / المستودع الرئيسي',
                    'supplier_number'        => "PROD-BATCH-{$i}",
                    'received_at'            => now()->subDays(rand(1, 60)),
                    'status'                 => 'approved_lab',
                    'total_price'            => $quantity * $price,
                    'warehouse_id'           => $userId,
                    'admin_approved_by'      => $userId,
                    'admin_approved_at'      => now(),
                    'warehouse_confirmed_by' => $userId,
                    'warehouse_confirmed_at' => now(),
                    'final_confirmed_by'     => $userId,
                    'final_confirmed_at'     => now(),
                    'notes'                  => "دفعة سكر أبيض جاهزة للبيع رقم {$i}",
                ]);

                ShipmentItem::create([
                    'shipment_id'        => $shipment->id,
                    'item_id'            => $sugarItem->id,
                    'quantity_required'  => $quantity,
                    'quantity_received'  => $quantity,
                    'quantity_reserved'  => 0,
                    'price'              => $price,
                    'unit_price'         => $price,
                    'expiry_date'        => now()->addMonths($i + 6)->format('Y-m-d'),
                    'lab_test_file'      => null,
                    'note'               => "دفعة سكر أبيض رقم {$i} بوزن {$quantity} طن",
                    'price_history'      => json_encode([['price' => $price, 'date' => now()->toDateTimeString()]]),
                    'quantity_history'   => json_encode([['quantity' => $quantity, 'date' => now()->toDateTimeString()]]),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. شحنات المواد الخام والمستلزمات - دفعات متعددة لكل مادة
        |--------------------------------------------------------------------------
        */
        $rawMaterialsConfig = [
            'سكر خام' => [
                'batches_count'  => 5,
                'qty_per_batch'  => 400,
                'base_price'     => 500,
                'expiry_months'  => 24,
                'supplier'       => 'شركة استيراد السكر الخام العالمية',
            ],
            'وقود (مازوت/فيول)' => [
                'batches_count'  => 4,
                'qty_per_batch'  => 37500,
                'base_price'     => 0.8,
                'expiry_months'  => 12,
                'supplier'       => 'شركة محروقات الوطنية',
            ],
            'كلس (هيدروكسيد الكالسيوم)' => [
                'batches_count'  => 3,
                'qty_per_batch'  => 13333.333333,
                'base_price'     => 0.15,
                'expiry_months'  => 18,
                'supplier'       => 'المورد العربي للكلس والكيماويات',
            ],
            'كيس سكر 50 كغ' => [
                'batches_count'  => 4,
                'qty_per_batch'  => 12500,
                'base_price'     => 0.5,
                'expiry_months'  => 36,
                'supplier'       => 'مصنع التغليف الحديث',
            ],
            'حمض الهيدروكلوريك (HCl)' => [
                'batches_count'  => 3,
                'qty_per_batch'  => 1666.666667,
                'base_price'     => 1.2,
                'expiry_months'  => 12,
                'supplier'       => 'المورد العربي للكلس والكيماويات',
            ],
            'ملح الطعام' => [
                'batches_count'  => 3,
                'qty_per_batch'  => 3333.333333,
                'base_price'     => 0.1,
                'expiry_months'  => 24,
                'supplier'       => 'شركة الملاحات الوطنية',
            ],
            'كلوريد الصوديوم النقي' => [
                'batches_count'  => 2,
                'qty_per_batch'  => 500,
                'base_price'     => 2.5,
                'expiry_months'  => 24,
                'supplier'       => 'تجهيزات المختبرات والكيماويات',
            ],
            'مخفف لزوجة (Viscosity Reducer)' => [
                'batches_count'  => 2,
                'qty_per_batch'  => 400,
                'base_price'     => 12.0,
                'expiry_months'  => 18,
                'supplier'       => 'تجهيزات المختبرات والكيماويات',
            ],
            'مانع رغوة (Antifoam)' => [
                'batches_count'  => 3,
                'qty_per_batch'  => 400,
                'base_price'     => 8.5,
                'expiry_months'  => 18,
                'supplier'       => 'تجهيزات المختبرات والكيماويات',
            ],
            'بريكوت (Precoat Filter Aid)' => [
                'batches_count'  => 3,
                'qty_per_batch'  => 1000,
                'base_price'     => 3.0,
                'expiry_months'  => 24,
                'supplier'       => 'المورد العربي للكلس والكيماويات',
            ],
            'ماء صناعي' => [
                'batches_count'  => 4,
                'qty_per_batch'  => 50000,
                'base_price'     => 0.01,
                'expiry_months'  => 12,
                'supplier'       => 'وحدة المعالجة المركزية',
            ],
        ];

        foreach ($rawMaterialsConfig as $itemName => $config) {
            $item = Item::where('name', $itemName)->first();
            if (!$item) {
                continue;
            }

            $batchesCount = $config['batches_count'];

            for ($b = 1; $b <= $batchesCount; $b++) {
                // تذبذب طفيف في السعر والكمية لكل دفعة
                $priceVariation = (rand(-5, 5) / 100); // 💡 نسبة تغيير +/- 5%
                $unitPrice = round($config['base_price'] * (1 + $priceVariation), 4);
                $quantity = $config['qty_per_batch'];
                $totalPrice = round($quantity * $unitPrice, 4);

                // تاريخ استقبال أقدم للدفعة الأولى وأحدث للدفعات التالية
                $receivedDaysAgo = ($batchesCount - $b + 1) * 15; 

                $shipment = Shipment::create([
                    'supplier'               => $config['supplier'],
                    'supplier_number'        => "SUP-INV-{$b}00{$item->id}",
                    'received_at'            => now()->subDays($receivedDaysAgo),
                    'status'                 => 'approved_lab',
                    'total_price'            => $totalPrice,
                    'warehouse_id'           => $userId,
                    'admin_approved_by'      => $userId,
                    'admin_approved_at'      => now()->subDays($receivedDaysAgo),
                    'warehouse_confirmed_by' => $userId,
                    'warehouse_confirmed_at' => now()->subDays($receivedDaysAgo),
                    'final_confirmed_by'     => $userId,
                    'final_confirmed_at'     => now()->subDays($receivedDaysAgo),
                    'notes'                  => "توريد دفعة رقم {$b} من مادة: {$item->name}",
                ]);

                ShipmentItem::create([
                    'shipment_id'        => $shipment->id,
                    'item_id'            => $item->id,
                    'quantity_required'  => $quantity,
                    'quantity_received'  => $quantity,
                    'quantity_reserved'  => 0,
                    'price'              => $totalPrice,
                    'unit_price'         => $unitPrice,
                    'expiry_date'        => now()->subDays($receivedDaysAgo)->addMonths($config['expiry_months'])->format('Y-m-d'),
                    'lab_test_file'      => null,
                    'note'               => "دفعة توريد رقم {$b} للمادة {$item->name} بكمية {$quantity}",
                    'price_history'      => json_encode([['price' => $unitPrice, 'date' => now()->subDays($receivedDaysAgo)->toDateTimeString()]]),
                    'quantity_history'   => json_encode([['quantity' => $quantity, 'date' => now()->subDays($receivedDaysAgo)->toDateTimeString()]]),
                ]);
            }
        }
    }
}
// class ShipmentSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $warehouseUser = User::first();

//         /*
//         |--------------------------------------------------------------------------
//         | 1. شحنات منتج نهائي (سكر أبيض) - 42 دفعة × 25 طن = 1050 طن
//         |--------------------------------------------------------------------------
//         */
//         $sugarItem = Item::where('name', 'سكر أبيض')->first();

//         if ($sugarItem) {
//             $totalBatches = 42;

//             for ($i = 1; $i <= $totalBatches; $i++) {
//                 $shipment = Shipment::create([
//                     'supplier'               => 'إنتاج داخلي / المستودع الرئيسي',
//                     'received_at'            => now()->subDays(rand(1, 30)),
//                     'status'                 => 'approved_lab',
//                     'warehouse_id'           => $warehouseUser->id ?? 1,
//                     'admin_approved_by'      => $warehouseUser->id ?? 1,
//                     'admin_approved_at'      => now(),
//                     'warehouse_confirmed_by' => $warehouseUser->id ?? 1,
//                     'warehouse_confirmed_at' => now(),
//                     'final_confirmed_by'     => $warehouseUser->id ?? 1,
//                     'final_confirmed_at'     => now(),
//                     'notes'                  => "دفعة سكر أبيض جاهزة للبيع رقم {$i}",
//                 ]);

//                 ShipmentItem::create([
//                     'shipment_id'        => $shipment->id,
//                     'item_id'            => $sugarItem->id,
//                     'quantity_required'  => 25,
//                     'quantity_received'  => 25,
//                     'price'              => rand(780, 810), // تكلفة الإنتاج للطن
//                     'expiry_date'        => now()->addMonths($i + 6)->format('Y-m-d'),
//                     'lab_test_file'      => null,
//                     'note'               => "دفعة سكر أبيض رقم {$i} بوزن 25 طن",
//                     'price_history'      => json_encode([['price' => 800, 'date' => now()->toDateTimeString()]]),
//                     'quantity_history'   => json_encode([['quantity' => 25, 'date' => now()->toDateTimeString()]]),
//                 ]);
//             }
//         }

//         /*
//         |--------------------------------------------------------------------------
//         | 2. شحنات المواد الخام والمستلزمات (كميات منطقية لاستمرار التشغيل)
//         |--------------------------------------------------------------------------
//         */
//         $rawMaterialsConfig = [
//             'سكر خام'                           => ['qty' => 2000,  'price' => 500,  'months' => 24], // طن
//             'وقود (مازوت/فيول)'                 => ['qty' => 150000, 'price' => 0.8,  'months' => 12], // ليتر
//             'كلس (هيدروكسيد الكالسيوم)'          => ['qty' => 40000,  'price' => 0.15, 'months' => 18], // كغ
//             'كيس سكر 50 كغ'                     => ['qty' => 50000,  'price' => 0.5,  'months' => 36], // قطعة
//             'حمض الهيدروكلوريك (HCl)'           => ['qty' => 5000,   'price' => 1.2,  'months' => 12], // كغ
//             'ملح الطعام'                         => ['qty' => 10000,  'price' => 0.1,  'months' => 24], // كغ
//             'كلوريد الصوديوم النقي'              => ['qty' => 1000,   'price' => 2.5,  'months' => 24], // كغ
//             'مخفف لزوجة (Viscosity Reducer)'    => ['qty' => 800,    'price' => 12.0, 'months' => 18], // كغ
//             'مانع رغوة (Antifoam)'              => ['qty' => 1200,   'price' => 8.5,  'months' => 18], // كغ
//             'بريكوت (Precoat Filter Aid)'       => ['qty' => 3000,   'price' => 3.0,  'months' => 24], // كغ
//             'ماء صناعي'                         => ['qty' => 200000, 'price' => 0.01, 'months' => 12], // ليتر
//         ];

//         $rawShipment = Shipment::create([
//             'supplier'               => 'موردو المواد الخام الكيماوية والمحروقات',
//             'received_at'            => now()->subDays(15),
//             'status'                 => 'approved_lab',
//             'warehouse_id'           => $warehouseUser->id ?? 1,
//             'admin_approved_by'      => $warehouseUser->id ?? 1,
//             'admin_approved_at'      => now(),
//             'warehouse_confirmed_by' => $warehouseUser->id ?? 1,
//             'warehouse_confirmed_at' => now(),
//             'final_confirmed_by'     => $warehouseUser->id ?? 1,
//             'final_confirmed_at'     => now(),
//             'notes'                  => 'شحنة توريد مواد خام تشغيلية لمصنع السكر',
//         ]);

//         foreach ($rawMaterialsConfig as $itemName => $config) {
//             $item = Item::where('name', $itemName)->first();
//             if ($item) {
//                 ShipmentItem::create([
//                     'shipment_id'       => $rawShipment->id,
//                     'item_id'           => $item->id,
//                     'quantity_required' => $config['qty'],
//                     'quantity_received' => $config['qty'],
//                     'price'             => $config['price'],
//                     'expiry_date'       => now()->addMonths($config['months'])->format('Y-m-d'),
//                     'lab_test_file'     => null,
//                     'note'              => "رصيد استفتحي للمادة: {$item->name}",
//                     'price_history'     => json_encode([['price' => $config['price'], 'date' => now()->toDateTimeString()]]),
//                     'quantity_history'  => json_encode([['quantity' => $config['qty'], 'date' => now()->toDateTimeString()]]),
//                 ]);
//             }
//         }
//     }
// }
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
//         | 1. إنشاء شحنات ودفعات خاصة بمادة "سكر أبيض"
//         | الهدف: أكثر من 1000 طن، موزعة 25 طن لكل دفعة بتواريخ انتهاء مختلفة
//         |--------------------------------------------------------------------------
//         */
//         $sugarItem = Item::where('name', 'سكر أبيض')->first();

//         if ($sugarItem) {
//             // 42 دفعة * 25 طن = 1050 طن (تجاوزنا الـ 1000 طن المطلوبة)
//             $totalBatches = 42; 

//             for ($i = 1; $i <= $totalBatches; $i++) {
                
//                 // إنشاء شحنة لكل دفعة لضمان استقلالية التواريخ والبيانات
//                 $shipment = Shipment::create([
//                     'supplier' => 'شركة السكر الوطنية الموردة',
//                     'received_at' => now()->subDays(rand(1, 10)),
//                     'status' => 'approved_lab',
//                     'warehouse_id' => $warehouseUser->id,
//                     'admin_approved_by' => $warehouseUser->id,
//                     'admin_approved_at' => now(),
//                     'warehouse_confirmed_by' => $warehouseUser->id,
//                     'warehouse_confirmed_at' => now(),
//                     'final_confirmed_by' => $warehouseUser->id,
//                     'final_confirmed_at' => now(),
//                     'notes' => 'شحنة سكر أبيض رقم ' . $i,
//                 ]);

//                 // إضافة دفعة السكر (25 طن)
//                 ShipmentItem::create([
//                     'shipment_id' => $shipment->id,
//                     'item_id' => $sugarItem->id,
//                     'quantity_required' => 25,
//                     'quantity_received' => 25,
//                     'price' => rand(1000, 1100), // سعر التكلفة (أقل من سعر المبيع 1200)
                    
//                     // 💡 تاريخ انتهاء مختلف لكل دفعة (نزيد شهر مع كل لفة بالـ loop)
//                     'expiry_date' => now()->addMonths($i + 6)->format('Y-m-d'),
                    
//                    // 'invoice_image' => null,
//                     'lab_test_file' => null,
//                     'note' => "دفعة سكر أبيض رقم {$i} بوزن 25 طن",
//                     'price_history' => json_encode([
//                         [
//                             'price' => 1100,
//                             'date' => now()->toDateTimeString(),
//                         ]
//                     ]),
//                     'quantity_history' => json_encode([
//                         [
//                             'quantity' => 25,
//                             'date' => now()->toDateTimeString(),
//                         ]
//                     ]),
//                 ]);
//             }
//         }

//         /*
//         |--------------------------------------------------------------------------
//         | 2. إنشاء شحنة تجريبية واحدة لباقي المواد (المواد الخام)
//         |--------------------------------------------------------------------------
//         */
//         $otherItems = Item::where('name', '!=', 'سكر أبيض')->get();

//         if ($otherItems->count() > 0) {
//             $shipment = Shipment::create([
//                 'supplier' => 'مورد المواد الخام',
//                 'received_at' => now()->subDays(2),
//                 'status' => 'approved_lab',
//                 'warehouse_id' => $warehouseUser->id,
//                 'admin_approved_by' => $warehouseUser->id,
//                 'admin_approved_at' => now(),
//                 'warehouse_confirmed_by' => $warehouseUser->id,
//                 'warehouse_confirmed_at' => now(),
//                 'final_confirmed_by' => $warehouseUser->id,
//                 'final_confirmed_at' => now(),
//                 'notes' => 'شحنة مواد خام أولية',
//             ]);

//             foreach ($otherItems as $item) {
//                 ShipmentItem::create([
//                     'shipment_id' => $shipment->id,
//                     'item_id' => $item->id,
//                     'quantity_required' => 5000, // كمية كبيرة من المواد الخام
//                     'quantity_received' => 5000,
//                     'price' => rand(10, 100),
//                     'expiry_date' => now()->addMonths(rand(12, 24))->format('Y-m-d'),
//                     //'invoice_image' => null,
//                     'lab_test_file' => null,
//                     'note' => 'دفعة للمادة الخام: ' . $item->name,
//                     'price_history' => json_encode([
//                         ['price' => 50, 'date' => now()->toDateTimeString()]
//                     ]),
//                     'quantity_history' => json_encode([
//                         ['quantity' => 5000, 'date' => now()->toDateTimeString()]
//                     ]),
//                 ]);
//             }
//         }
//     }
// }

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