<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            // 1. المواد الخام الأساسية (Raw Materials)
            [
                'ar_name' => 'مواد خام أساسية',
                'en_name' => 'Raw Materials',
            ],
            // 2. المواد الكيميائية والإضافات الكيميائية (Chemicals & Processing Aids)
            [
                'ar_name' => 'مواد كيميائية ومعالجة',
                'en_name' => 'Chemicals & Processing Aids',
            ],
            // 3. مستلزمات التعبئة والتغليف (Packaging Materials)
            [
                'ar_name' => 'مواد التغليف والتعبئة',
                'en_name' => 'Packaging Materials',
            ],
            // 4. المنتجات النهائية (Finished Products)
            [
                'ar_name' => 'منتج نهائي',
                'en_name' => 'Finished Products',
            ],
            // 5. الطاقة والمرافق (Energy & Utilities)
            [
                'ar_name' => 'طاقة ومرافق',
                'en_name' => 'Energy & Utilities',
            ],
            // 6. المنتجات الثانوية (By-Products)
            // [
            //     'ar_name' => 'منتجات ثانوية',
            //     'en_name' => 'By-Products',
            // ],
        ];

        foreach ($sections as $section) {
            Section::query()->firstOrCreate(
                ['en_name' => $section['en_name']],
                $section
            );
        }
    }
}
// class SectionSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         Section::query()->create([
//             'ar_name' => 'مواد خام',
//             'en_name' => 'Raw Materials',
//         ]);
//         Section::query()->create([
//             'ar_name' => 'تغليف',
//             'en_name' => 'Packaging',
//         ]);
//         Section::query()->create([
//             'ar_name' => 'منتج نهائي',
//             'en_name' => 'Finished Product',
//         ]);
//     }
// }
