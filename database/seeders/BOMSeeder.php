<?php


namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BOMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // جلب المواد من قاعدة البيانات بالاسم لضمان الدقة
        $finalItem = Item::where('name', 'سكر أبيض')->first();
        $sugarCane = Item::where('name', 'قصب السكر')->first();
        $water = Item::where('name', 'ماء')->first();
        $chemicals = Item::where('name', 'مواد كيميائية')->first();

        // التأكد من وجود المواد قبل الإدخال
        if (!$finalItem || !$sugarCane || !$water || !$chemicals) {
            $this->command->error('الرجاء التأكد من تشغيل ItemSeeder أولاً لتوفير المواد الأساسية.');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | التركيبة (BOM): لإنتاج 100 كغ من السكر الأبيض نحتاج إلى:
        | - 1000 كغ قصب سكر (المادة الأساسية)
        | - 50 ليتر ماء (مادة ثانوية)
        | - 5 كغ مواد كيميائية (مادة ثانوية)
        |--------------------------------------------------------------------------
        */

        $boms = [
            [
                'final_item_id'       => $finalItem->id,
                'basic_item_id'       => $sugarCane->id,
                'is_primary'          => true, // 👈 هذه هي المادة الأساسية التي يبنى عليها الإنتاج
                'basic_item_quantity' => 1000,
                'final_item_quantity' => 100,  // 👈 كمية المنتج النهائي التي تنتج عن هذه التركيبة
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
            [
                'final_item_id'       => $finalItem->id,
                'basic_item_id'       => $water->id,
                'is_primary'          => false, // مادة ثانوية
                'basic_item_quantity' => 50,
                'final_item_quantity' => 100,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
            [
                'final_item_id'       => $finalItem->id,
                'basic_item_id'       => $chemicals->id,
                'is_primary'          => false, // مادة ثانوية
                'basic_item_quantity' => 5,
                'final_item_quantity' => 100,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        ];

        DB::table('b_o_m_s')->insert($boms);
    }
}

// namespace Database\Seeders;

// use App\Models\Item;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
// use Illuminate\Database\Seeder;
// use Illuminate\Support\Facades\DB;

// class BOMSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {

//         /*
//         |--------------------------------------------------------------------------
//         | BOM: first item = final product
//         |--------------------------------------------------------------------------
//         */
//         $items = Item::get()->toArray();
//         $finalItem = $items[0]; // سكر أبيض

//         foreach (array_slice($items, 1) as $basicItem) {
//             DB::table('b_o_m_s')->insert([
//                 'final_item_id' => $finalItem['id'],
//                 'basic_item_id' => $basicItem['id'],
//                 'basic_item_quantity' => 40,
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ]);
//         }
//     }
// }
