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
        $section = Section::first();

        // التعديل هنا: استخدام -> بدلاً من ::
        if ($users->isEmpty()) {
            return;
        }

        // أسباب إتلاف واقعية تشغيلية داخل معمل السكر
        $demolishReasons = [
            'expired_chemical'  => 'انتهاء صلاحية مادة كيميائية (مخفف لزوجة / مانع رغوة)',
            'humidity_damage'   => 'تلف وتكتل جراء التسرب أو الرطوبة العالية بالمستودع',
            'package_damage'    => 'تمزق الأكياس واختلاط البضاعة بالأتربة أثناء التحميل',
            'lab_rejection'     => 'رفض العينة مخبرياً وعدم صلاحيتها للاستخدام التشغيلي',
            'fermentation'      => 'تلف ميكروبي / تخمر بسبب سوء التخزين',
        ];

        $statuses = ['pending', 'approved', 'rejected', 'completed'];

        foreach ($statuses as $status) {
            for ($i = 0; $i < 3; $i++) {
                // اختيار مادة عشوائية موجودة في الشحنات
                $shipmentItem = ShipmentItem::with('item')->inRandomOrder()->first();

                if (!$shipmentItem || !$shipmentItem->item) {
                    continue;
                }

                $item = $shipmentItem->item;

                // تحديد كمية إتلاف منطقية حسب نوع المادة
                $quantity = match ($item->name) {
                    'كيس سكر 50 كغ'                     => rand(50, 300), // قطع أكياس متضررة
                    'سكر أبيض', 'سكر خام'               => rand(1, 5),     // أطنان
                    'وقود (مازوت/فيول)', 'ماء صناعي'    => rand(100, 500), // ليتر
                    default                             => rand(5, 25),    // كغ للمواد الكيماوية
                };

                $reasonKey = array_rand($demolishReasons);
                $reasonText = $demolishReasons[$reasonKey];

                DemolishOrder::create([
                    'section_id'  => $section->id ?? 1,
                    'item_id'     => $item->id,
                    'shipment_id' => $shipmentItem->shipment_id,
                    'quantity'    => $quantity,
                    'reason'      => $reasonText,
                    'status'      => $status,
                    'created_by'  => $users->random()->id,
                ]);
            }
        }
    }
}
// class DemolishOrderSeeder extends Seeder
// {
//     public function run(): void
//     {
//         $users = User::all();

//         $statuses = [
//             'pending',
//             'approved',
//             'rejected',
//             'completed',
//         ];

//         foreach ($statuses as $status) {

//             for ($i = 0; $i < 5; $i++) {

//                 $item = Item::inRandomOrder()->first();

//                 $shipment = ShipmentItem::where(
//                     'item_id',
//                     $item->id
//                 )->first();

//                 DemolishOrder::create([

//                     'section_id' => Section::inRandomOrder()->first()->id,

//                     'item_id' => $item->id,

//                     'shipment_id' => $shipment?->shipment_id,

//                     'quantity' => rand(1, 50),

//                     'reason' => 'Expired material',

//                     'status' => $status,

//                     'created_by' => $users->random()->id,
//                 ]);
//             }
//         }
//     }
// }
