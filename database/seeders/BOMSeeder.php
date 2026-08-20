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
        // 1. جلب المنتج النهائي (1 طن سكر أبيض)
        $finalItem = Item::where('name', 'سكر أبيض')->first();

        if (!$finalItem) {
            $this->command->error('الرجاء التأكد من وجود "سكر أبيض" في ItemSeeder أولاً.');
            return;
        }

        // 2. تعريف قائمة المكونات والكميات اللازمة لإنتاج 1 طن من السكر الأبيض
        $ingredients = [
            // المادة الأساسية (Primary)
            [
                'name' => 'سكر خام',
                'quantity' => 1.0000,
                'is_primary' => true,
            ],
            // المواد الثانوية (Secondary)
            [
                'name' => 'وقود (مازوت/فيول)',
                'quantity' => 65.0000,
                'is_primary' => false,
            ],
            [
                'name' => 'كلس (هيدروكسيد الكالسيوم)',
                'quantity' => 20.0000,
                'is_primary' => false,
            ],
            [
                'name' => 'كيس سكر 50 كغ',
                'quantity' => 20.0000, // 20 كيس تعبئة لإنتاج 1 طن (1000 كغ / 50 كغ)
                'is_primary' => false,
            ],
            [
                'name' => 'حمض الهيدروكلوريك (HCl)',
                'quantity' => 0.0030,
                'is_primary' => false,
            ],
            [
                'name' => 'ملح الطعام',
                'quantity' => 0.4400,
                'is_primary' => false,
            ],
            [
                'name' => 'كلوريد الصوديوم النقي',
                'quantity' => 0.0003,
                'is_primary' => false,
            ],
            [
                'name' => 'مخفف لزوجة (Viscosity Reducer)',
                'quantity' => 0.0001,
                'is_primary' => false,
            ],
            [
                'name' => 'مانع رغوة (Antifoam)',
                'quantity' => 0.0004,
                'is_primary' => false,
            ],
            [
                'name' => 'بريكوت (Precoat Filter Aid)',
                'quantity' => 0.5800,
                'is_primary' => false,
            ],
            [
                'name' => 'ماء صناعي',
                'quantity' => 1.0500,
                'is_primary' => false,
            ],
        ];

        $boms = [];

        foreach ($ingredients as $ingredient) {
            // البحث عن المادة بالاسم (أو ببدائل الاسم في حال اختلاف الـ Seeder)
            $basicItem = Item::where('name', $ingredient['name'])->first();

            if ($basicItem) {
                $boms[] = [
                    'final_item_id'       => $finalItem->id,
                    'basic_item_id'       => $basicItem->id,
                    'is_primary'          => $ingredient['is_primary'],
                    'basic_item_quantity' => $ingredient['quantity'],
                    'final_item_quantity' => 1, // 1 طن سكر أبيض
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            } else {
                $this->command->warn("المادة '{$ingredient['name']}' غير موجودة في جدول المواد وتم تجاوزها.");
            }
        }

        // تفريغ الجدول قبل الإدخال لمنع التكرار
        DB::table('b_o_m_s')->where('final_item_id', $finalItem->id)->delete();

        if (!empty($boms)) {
            DB::table('b_o_m_s')->insert($boms);
            $this->command->info('تم إدخال وصفة تصنيع السكر الأبيض بنجاح.');
        }
    }
}
// class BOMSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         // جلب المواد من قاعدة البيانات بالاسم لضمان الدقة
//         $finalItem = Item::where('name', 'سكر أبيض')->first();
//         $sugarCane = Item::where('name', 'قصب السكر')->first();
//         $water = Item::where('name', 'ماء')->first();
//         $chemicals = Item::where('name', 'مواد كيميائية')->first();

//         // التأكد من وجود المواد قبل الإدخال
//         if (!$finalItem || !$sugarCane || !$water || !$chemicals) {
//             $this->command->error('الرجاء التأكد من تشغيل ItemSeeder أولاً لتوفير المواد الأساسية.');
//             return;
//         }

//         /*
//         |--------------------------------------------------------------------------
//         | التركيبة (BOM): لإنتاج 100 كغ من السكر الأبيض نحتاج إلى:
//         | - 1000 كغ قصب سكر (المادة الأساسية)
//         | - 50 ليتر ماء (مادة ثانوية)
//         | - 5 كغ مواد كيميائية (مادة ثانوية)
//         |--------------------------------------------------------------------------
//         */

//         $boms = [
//             [
//                 'final_item_id'       => $finalItem->id,
//                 'basic_item_id'       => $sugarCane->id,
//                 'is_primary'          => true, // 👈 هذه هي المادة الأساسية التي يبنى عليها الإنتاج
//                 'basic_item_quantity' => 1000,
//                 'final_item_quantity' => 100,  // 👈 كمية المنتج النهائي التي تنتج عن هذه التركيبة
//                 'created_at'          => now(),
//                 'updated_at'          => now(),
//             ],
//             [
//                 'final_item_id'       => $finalItem->id,
//                 'basic_item_id'       => $water->id,
//                 'is_primary'          => false, // مادة ثانوية
//                 'basic_item_quantity' => 50,
//                 'final_item_quantity' => 100,
//                 'created_at'          => now(),
//                 'updated_at'          => now(),
//             ],
//             [
//                 'final_item_id'       => $finalItem->id,
//                 'basic_item_id'       => $chemicals->id,
//                 'is_primary'          => false, // مادة ثانوية
//                 'basic_item_quantity' => 5,
//                 'final_item_quantity' => 100,
//                 'created_at'          => now(),
//                 'updated_at'          => now(),
//             ]
//         ];

//         DB::table('b_o_m_s')->insert($boms);
//     }
// }

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
