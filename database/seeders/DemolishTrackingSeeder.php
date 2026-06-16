<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ItemTrackingLog;
use App\Models\DemolishOrder;

class DemolishTrackingSeeder extends Seeder
{
    public function run(): void
    {
        $warehouseUser = User::first();

        $orders = DemolishOrder::with('item')->get();

        foreach ($orders as $order) {

            ItemTrackingLog::create([

                'type' => 'اتلاف',

                'trackable_id' => $order->id,

                'trackable_type' => DemolishOrder::class,

                'status' => $order->status,

                'item_id' => $order->item_id,

                'item_name' => $order->item->name,

                'quantity' => $order->quantity,

                'shipment_id' => $order->shipment_id,

                'sent_from_role' => 'warehouse',

                'sent_from_user_name' => $warehouseUser->name,

                'sent_from_user_id' => $warehouseUser->id,

                'sent_to_role' => 'demolish',

                'sent_to_user_name' => 'Demolish Department',

                'sent_to_user_id' => 0,

                'notes' =>
                    "Demolish order #{$order->id}",
            ]);
        }
    }
}
