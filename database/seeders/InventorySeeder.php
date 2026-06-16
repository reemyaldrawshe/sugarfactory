<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Inventory;
use App\Models\InventoryItem;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $inventory = Inventory::create([
            'date' => now(),
            'status' => 'pending',
            'overall_compatibility' => 0,
            'notes' => 'Initial test inventory'
        ]);

        InventoryItem::create([
            'inventory_id' => $inventory->id,
            'item_id' => 1,
            'purchased_quantity' => 100,
            'system_quantity' => 95,
            'actual_quantity' => 90,
            'difference' => -5,
            'compatibility_percent' => 90,
            'notes' => 'Minor shortage'
        ]);
    }
}
