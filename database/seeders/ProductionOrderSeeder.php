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
        | المنتج النهائي
        |--------------------------------------------------------------------------
        */
        $finalItem = Item::first();

        $warehouseUser = User::first();

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
                'warehouse_id' => 2,
                'production_id' => 6,
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

        $orders = ProductionOrder::with([
            'materials.item'
        ])->get();

        foreach ($orders as $order) {

            /*
            |--------------------------------------------------------------------------
            | Only orders that reached production
            |--------------------------------------------------------------------------
            */
            if (
                !in_array(
                    $order->status,
                    [
                        'sent_to_production',
                        'started',
                        'passed',
                        'completed'
                    ]
                )
            ) {
                continue;
            }

            foreach ($order->materials as $material) {

                ItemTrackingLog::create([

                    'type' => 'صرف',

                    'trackable_id' => $order->id,

                    'trackable_type' => ProductionOrder::class,

                    'status' => $order->status,

                    'item_id' => $material->item_id,

                    'item_name' => $material->item->name,

                    'quantity' => $material->consumed_quantity,

                    'shipment_id' => null,

                    'sent_from_role' => 'warehouse',

                    'sent_from_user_name' => $warehouseUser->name,

                    'sent_from_user_id' => $warehouseUser->id,

                    'sent_to_role' => 'production',

                    'sent_to_user_name' => 'Production Department',

                    'sent_to_user_id' => 0,

                    'notes' =>
                        "Materials issued for production order #{$order->id}",
                ]);
            }
        }
    }
}
