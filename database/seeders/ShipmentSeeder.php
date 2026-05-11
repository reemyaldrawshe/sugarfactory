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
        | إنشاء شحنات
        |--------------------------------------------------------------------------
        */
        for ($i = 1; $i <= 3; $i++) {

            $shipment = Shipment::create([

                'supplier' => 'Supplier ' . $i,

                'received_at' => now()->subDays($i),

                'status' => 'approved_lab',

                'warehouse_id' => $warehouseUser->id,

                'admin_approved_by' => $warehouseUser->id,
                'admin_approved_at' => now(),

                'warehouse_confirmed_by' => $warehouseUser->id,
                'warehouse_confirmed_at' => now(),

                'final_confirmed_by' => $warehouseUser->id,
                'final_confirmed_at' => now(),

                'notes' => 'شحنة تجريبية رقم ' . $i,
            ]);

            /*
            |--------------------------------------------------------------------------
            | عناصر الشحنة
            |--------------------------------------------------------------------------
            */
            $items = Item::all();

            foreach ($items as $item) {

                ShipmentItem::create([

                    'shipment_id' => $shipment->id,

                    'item_id' => $item->id,

                    'quantity_required' => 500,

                    'quantity_received' => rand(3000, 5000),

                    'price' => rand(10, 100),

                    'expiry_date' => now()
                        ->addMonths(rand(3, 12))
                        ->format('Y-m-d'),

                    'invoice_image' => null,

                    'lab_test_file' => null,

                    'note' => 'دفعة للمادة ' . $item->name,

                    'price_history' => json_encode([
                        [
                            'price' => rand(10, 100),
                            'date' => now(),
                        ]
                    ]),

                    'quantity_history' => json_encode([
                        [
                            'quantity' => rand(300, 500),
                            'date' => now(),
                        ]
                    ]),
                ]);
            }
        }
    }
}