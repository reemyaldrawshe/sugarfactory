<?php

namespace Database\Seeders;

use App\Models\BOM;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\ShipmentItem;
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
        | المنتج النهائي
        |--------------------------------------------------------------------------
        */
        $finalItem = Item::first();

        /*
        |--------------------------------------------------------------------------
        | Orders
        |--------------------------------------------------------------------------
        */

        $orders = [

            [
                'quantity' => 50,
                'produced_quantity' => 0,
                'status' => 'approved_by_manager',
                'notes' => 'طلب جديد بانتظار التحضير',
            ],
            

            [
                'quantity' => 50,
                'produced_quantity' => 0,
                'status' => 'materials_reserved',
                'notes' => 'المواد محجوزة',
            ],

            [
                'quantity' => 50,
                'produced_quantity' => 120,
                'status' => 'sent_to_production',
                'notes' => 'إنتاج جزئي',
            ],

             [
                'quantity' => 50,
                'produced_quantity' => 120,
                'status' => 'started',
                'notes' => 'إنتاج جزئي',
            ],
             [
                'quantity' => 50,
                'produced_quantity' => 120,
                'status' => 'passed',
                'notes' => 'إنتاج جزئي',
            ],

            [
                'quantity' => 50,
                'produced_quantity' => 300,
                'status' => 'completed',
                'notes' => 'تم الإنتاج بالكامل',
            ],
        ];

        foreach ($orders as $data) {

            $order = ProductionOrder::create([
                'item_id' => $finalItem->id,
                'quantity' => $data['quantity'],
                'produced_quantity' => $data['produced_quantity'],
                'status' => $data['status'],
                'notes' => $data['notes'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | إذا مو طلب جديد → أضف مواد مصروفة
            |--------------------------------------------------------------------------
            */
            if ($data['status'] !== 'approved_by_manager') {

                $boms = BOM::where(
                    'final_item_id',
                    $finalItem->id
                )->get();

                foreach ($boms as $bom) {

                    $required =
                        $bom->basic_item_quantity
                        * $order->quantity;

                    /*
                    |--------------------------------------------------------------------------
                    | دفعة للمادة
                    |--------------------------------------------------------------------------
                    */
                    $batch = ShipmentItem::where(
                        'item_id',
                        $bom->basic_item_id
                    )->first();

                    if (!$batch) {
                        continue;
                    }

                    ProductionOrderMaterial::create([

                        'production_order_id' => $order->id,

                        'item_id' => $bom->basic_item_id,

                        'shipment_item_id' => $batch->id,

                        'required_quantity' => $required,

                        'consumed_quantity' => min(
                            $required,
                            100
                        ),
                    ]);
                }
            }
        }
    }
}