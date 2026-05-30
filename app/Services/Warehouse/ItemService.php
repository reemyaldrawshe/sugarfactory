<?php

namespace App\Services\Warehouse;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class ItemService
{
    /*
    |--------------------------------------------------------------------------
    | Index (List with filters)
    |--------------------------------------------------------------------------
    */
    public function index(array $filters = []): Collection
    {
        return Item::query()
        // هنا نقوم بجلب العلاقات: القسم، الوحدة، والدفعات
        ->with(['section', 'unit', 'shipmentItems']) 
        
            ->when(isset($filters['section_id']), function ($q) use ($filters) {
                $q->where('section_id', $filters['section_id']);
            })
            ->when(isset($filters['unit_id']), function ($q) use ($filters) {
                $q->where('unit_id', $filters['unit_id']);
            })
            ->when(isset($filters['is_raw_material']), function ($q) use ($filters) {
                $q->where('is_raw_material', $filters['is_raw_material']);
            })
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%');
            })
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */
    public function store(array $data): Item
    {
        $item = Item::create(collect($data)->except('image')->toArray());

        if (isset($data['image'])) {
            $media = $item->addMediaFromRequest('image')
                ->toMediaCollection('item_image');
            $item['image'] = $media->getFullUrl();
            $item->save();
        }
        return $item->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */
    public function show($item): Item
    {
        return $item->with(['section', 'unit', 'shipmentItems'])->find($item['id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */
    public function update($item, array $data): Item
    {

        $item->update(collect($data)->except('image')->toArray());

        // فقط إذا تم إرسال صورة جديدة
        if (isset($data['image'])) {
            // حذف الصور القديمة
            $item->clearMediaCollection('item_image');

            // إضافة الصورة الجديدة
            $media = $item->addMediaFromRequest('image')
                ->toMediaCollection('item_image');

            $item['image'] = $media->getFullUrl();
            $item->save();
        }

        return $item->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */
    public function delete($item)
    {
        // حذف الصور المرتبطة
        $item->clearMediaCollection('item_image');

        return $item->delete();
    }
}
