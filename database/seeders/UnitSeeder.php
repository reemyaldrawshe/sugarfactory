<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            // وحدات الكتلة والوزن
            ['name' => 'kg'],          // كيلوغرام (موجود)
            ['name' => 'ton'],         // طن (لاستلام الشمندر/القصب والإنتاج الضخم)
            //['name' => 'gram'],        // غرام (للفحوصات المخبرية والمواد الكيميائية)
           // ['name' => 'mg'],          // ميليغرام (للمختبر والدقة العالية)
            
            // وحدات الحجم والسوائل
            ['name' => 'liter'],       // ليتر (موجود)
         //   ['name' => 'm3'],          // متر مكعب (لمياه الغسيل، العصارة، والشراب المركز)
          //  ['name' => 'ml'],          // ميليليتر (للمعايرة المخبرية)
            
            // وحدات التعبئة والقطع
            ['name' => 'piece'],       // قطعة (موجود)
        //    ['name' => 'bag_50kg'],    // كيس 50 كغ (التعبئة القياسية للسكر)
         //   ['name' => 'bag_25kg'],    // كيس 25 كغ
         //   ['name' => 'big_bag'],     // جامبو باج / كيس كبير (1 طن)
          //  ['name' => 'carton'],      // كرتونة (للمكعبات أو الأكياس الصغيرة)
          //  ['name' => 'pallet'],      // طبلية / بالت (للتخزين والشحن)

            // وحدات القياس الخاصة بالعمليات والتصنيع
          //  ['name' => 'brix'],        // بركس (لقياس نسبة السكر في المحاليل والعصير)
          //  ['name' => 'bar'],         // بار (لقياس ضغط البخار والمرلاجل)
          //  ['name' => 'celsius'],     // درجة مئوية (لعمليات التبخير والتكريستال)
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['name' => $unit['name']]);
        }
    }
}
// class UnitSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
 

// public function run(): void
// {
//     Unit::create(['name' => 'kg']);
//     Unit::create(['name' => 'liter']);
//     Unit::create(['name' => 'piece']);
//     Unit::create(['name' => 'ton']);

// }
// }
