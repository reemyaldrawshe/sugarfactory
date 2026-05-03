<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BOMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        /*
        |--------------------------------------------------------------------------
        | BOM: first item = final product
        |--------------------------------------------------------------------------
        */
        $items = Item::get()->toArray();
        $finalItem = $items[0]; // سكر أبيض

        foreach (array_slice($items, 1) as $basicItem) {
            DB::table('b_o_m_s')->insert([
                'final_item_id' => $finalItem['id'],
                'basic_item_id' => $basicItem['id'],
                'basic_item_quantity' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
