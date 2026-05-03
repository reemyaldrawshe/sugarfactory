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

        $items = [
            [
                'name' => 'سكر أبيض',
                'image' => public_path('seeder/images/sugar-svgrepo-com.svg'),
                'unit_id' => Unit::query()->where('name', '=', 'kg')->first()['id'],
                'section_id' => Section::query()->where('ar_name', '=', 'منتج نهائي')->first()['id'],
            ],
            [
                'name' => 'قصب السكر',
                'image' => public_path('seeder/images/sugar-cane-svgrepo-com.svg'),
                'unit_id' => Unit::query()->where('name', '=', 'kg')->first()['id'],
                'section_id' => Section::query()->where('ar_name', '=', 'مواد خام')->first()['id'],
            ],
            [
                'name' => 'ماء',
                'image' => public_path('seeder/images/water-tank-svgrepo-com.svg'),
                'unit_id' => Unit::query()->where('name', '=', 'liter')->first()['id'],
                'section_id' => Section::query()->where('ar_name', '=', 'مواد خام')->first()['id'],
            ],
            [
                'name' => 'مواد كيميائية',
                'image' => public_path('seeder/images/test-tubes-chemical-svgrepo-com.svg'),
                'unit_id' => Unit::query()->where('name', '=', 'kg')->first()['id'],
                'section_id' => Section::query()->where('ar_name', '=', 'مواد خام')->first()['id'],
            ],
        ];

        foreach ($items as $data) {
            $item = Item::create([
                'name' => $data['name'],
                'section_id' => $data['section_id'],
                'unit_id' => $data['unit_id'],
                'is_raw_material' => true,
            ]);

            // إضافة صورة إذا موجودة
            if (file_exists($data['image'])) {
                $media = $item->addMedia($data['image'])
                    ->preservingOriginal()
                    ->toMediaCollection('item_image');
                $item['image'] = $media->getFullUrl();
                $item->save();
            }
        }
    }
}
