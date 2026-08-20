<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Section;
use App\Models\Unit;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        // جلب الأقسام والوحدات كـ Map لتسهيل الوصول وتفادي الاستعلامات المكررة
        $sections = Section::pluck('id', 'ar_name');
        $units = Unit::pluck('id', 'name');

        $items = [
            // 1. المنتجات النهائية والجانبية (تُباع)
            [
                'name' => 'سكر أبيض',
                'unit_id' => $units['ton'] ?? null,
                'section_id' => $sections['منتج نهائي'] ?? null,
                'is_raw_material' => false,
                'selling_price' => 850.00, // السعر العالمي المقارب للطن ($850)
                //'min_quantity' => 100,     // حد أدنى 100 طن لضمان استمرارية التوريد
            ],
            // [
            //     'name' => 'أملاس (ملاذ)',
            //     'unit_id' => $units['ton'] ?? $units['kg'] ?? null,
            //     'section_id' => $sections['منتجات ثانوية'] ?? $sections['منتج نهائي'] ?? null,
            //     'is_raw_material' => false,
            //     'selling_price' => 180.00, // منتج ثانوي يُباع لمعامل الأعلاف/الكحول (~$180/طن)
            //     'min_quantity' => 50,      // حد أدنى 50 طن
            // ],

            // 2. المواد الخام الأساسية
            [
                'name' => 'سكر خام',
                'unit_id' => $units['ton'] ?? null,
                'section_id' => $sections['مواد خام أساسية'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
               // 'min_quantity' => 500,     // المعمل يحتاج مخزون ضخم لتشغيل الخطوط دون توقف
            ],

            // 3. المواد الكيميائية ومعالجة التصنيع
            [
                'name' => 'كلس (هيدروكسيد الكالسيوم)',
                'unit_id' => $units['kg'] ?? null,
                'section_id' => $sections['مواد كيميائية ومعالجة'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
             //   'min_quantity' => 5000,    // 5 طن (يُستهلك بكثرة في عملية الترويق)
            ],
            [
                'name' => 'حمض الهيدروكلوريك (HCl)',
                'unit_id' => $units['kg'] ?? null,
                'section_id' => $sections['مواد كيميائية ومعالجة'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
                //'min_quantity' => 2000,    // 2 طن (غسيل المراجل والمبخرات)
            ],
            [
                'name' => 'ملح الطعام',
                'unit_id' => $units['kg'] ?? null,
                'section_id' => $sections['مواد كيميائية ومعالجة'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
               // 'min_quantity' => 1000,    // 1 طن (لإعادة تنشيط اليسر/الماء)
            ],
            [
                'name' => 'كلوريد الصوديوم النقي',
                'unit_id' => $units['kg'] ?? null,
                'section_id' => $sections['مواد كيميائية ومعالجة'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
               // 'min_quantity' => 500,     // 500 كغ (للمختبر والمعالجة الخاصة)
            ],
            [
                'name' => 'مخفف لزوجة (Viscosity Reducer)',
                'unit_id' => $units['kg'] ?? null,
                'section_id' => $sections['مواد كيميائية ومعالجة'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
                //'min_quantity' => 300,     // 300 كغ
            ],
            [
                'name' => 'مانع رغوة (Antifoam)',
                'unit_id' => $units['kg'] ?? null,
                'section_id' => $sections['مواد كيميائية ومعالجة'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
                //'min_quantity' => 400,     // 400 كغ (مهم جداً في أجهزة التبخير)
            ],
            [
                'name' => 'بريكوت (Precoat Filter Aid)',
                'unit_id' => $units['kg'] ?? null,
                'section_id' => $sections['مواد كيميائية ومعالجة'] ?? $sections['موad خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
               // 'min_quantity' => 1500,    // 1.5 طن (مادة ترشيح للمشرحات)
            ],

            // 4. مواد التغليف والتعبئة
            [
                'name' => 'كيس سكر 50 كغ',
                'unit_id' => $units['piece'] ?? null,
                'section_id' => $sections['مواد التغليف والتعبئة'] ?? $sections['تغليف'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
             //   'min_quantity' => 10000,   // 10,000 قطعة لتغطية إنتاج 500 طن سكر
            ],

            // 5. الطاقة والمرافق
            [
                'name' => 'وقود (مازوت/فيول)',
                'unit_id' => $units['liter'] ?? null,
                'section_id' => $sections['طاقة ومرافق'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
               // 'min_quantity' => 25000,   // 25 ألف ليتر لتشغيل الغلايات والمولدات
            ],
            [
                'name' => 'ماء صناعي',
                'unit_id' => $units['liter'] ?? $units['m3'] ?? null,
                'section_id' => $sections['طاقة ومرافق'] ?? $sections['مواد خام'] ?? null,
                'is_raw_material' => true,
                'selling_price' => null,
                //'min_quantity' => 50000,   // 50 ألف ليتر (عمليات التبريد والغسيل)
            ],
        ];

        foreach ($items as $data) {
            Item::firstOrCreate(
                ['name' => $data['name']],
                [
                    'section_id' => $data['section_id'],
                    'unit_id' => $data['unit_id'],
                    'is_raw_material' => $data['is_raw_material'],
                    'selling_price' => $data['selling_price'],
                   // 'min_quantity' => $data['min_quantity'],
                ]
            );
        }
    }
}
// class ItemSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $items = [
//             [
//                 'name' => 'سكر أبيض',
//               //  'image' => public_path('seeder/images/sugar-svgrepo-com.svg'),
//                 'unit_id' => Unit::query()->where('name', '=', 'kg')->first()['id'],
//                 'section_id' => Section::query()->where('ar_name', '=', 'منتج نهائي')->first()['id'],
//                 'is_raw_material' => false,
//                 'selling_price' => 1200.00, // سعر بيع الطن للمنتج النهائي
//             ],
//             [
//                 'name' => 'قصب السكر',
//               //  'image' => public_path('seeder/images/sugar-cane-svgrepo-com.svg'),
//                 'unit_id' => Unit::query()->where('name', '=', 'kg')->first()['id'],
//                 'section_id' => Section::query()->where('ar_name', '=', 'مواد خام')->first()['id'],
//                 'is_raw_material' => true,
//                 'selling_price' => null, // مادة خام لا تباع
//             ],
//             [
//                 'name' => 'ماء',
//               //  'image' => public_path('seeder/images/water-tank-svgrepo-com.svg'),
//                 'unit_id' => Unit::query()->where('name', '=', 'liter')->first()['id'],
//                 'section_id' => Section::query()->where('ar_name', '=', 'مواد خام')->first()['id'],
//                 'is_raw_material' => true,
//                 'selling_price' => null, // مادة خام لا تباع
//             ],
//             [
//                 'name' => 'مواد كيميائية',
//                // 'image' => public_path('seeder/images/test-tubes-chemical-svgrepo-com.svg'),
//                 'unit_id' => Unit::query()->where('name', '=', 'kg')->first()['id'],
//                 'section_id' => Section::query()->where('ar_name', '=', 'مواد خام')->first()['id'],
//                 'is_raw_material' => true,
//                 'selling_price' => null, // مادة خام لا تباع
//             ],
//         ];

//         foreach ($items as $data) {
//             $item = Item::create([
//                 'name' => $data['name'],
//                 'section_id' => $data['section_id'],
//                 'unit_id' => $data['unit_id'],
//                 'is_raw_material' => $data['is_raw_material'],
//                 'selling_price' => $data['selling_price'], // إدراج السعر هنا
//             ]);

//             // // إضافة صورة إذا كانت موجودة
//             // if (file_exists($data['image'])) {
//             //     $media = $item->addMedia($data['image'])
//             //         ->preservingOriginal()
//             //         ->toMediaCollection('item_image');
                
//             //     // تحديث حقل الصورة بالرابط الكامل
//             //     $item->update([
//             //         'image' => $media->getFullUrl()
//             //     ]);
//             // }
//         }
//     }
// }