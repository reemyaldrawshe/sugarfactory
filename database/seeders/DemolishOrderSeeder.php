<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Item;
use App\Models\Section;
use App\Models\ShipmentItem;
use App\Models\DemolishOrder;

class DemolishOrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $statuses = [
            'pending',
            'approved',
            'rejected',
            'completed',
        ];

        foreach ($statuses as $status) {

            for ($i = 0; $i < 5; $i++) {

                $item = Item::inRandomOrder()->first();

                $shipment = ShipmentItem::where(
                    'item_id',
                    $item->id
                )->first();

                DemolishOrder::create([

                    'section_id' => Section::inRandomOrder()->first()->id,

                    'item_id' => $item->id,

                    'shipment_id' => $shipment?->shipment_id,

                    'quantity' => rand(1, 50),

                    'reason' => 'Expired material',

                    'status' => $status,

                    'created_by' => $users->random()->id,
                ]);
            }
        }
    }
}
