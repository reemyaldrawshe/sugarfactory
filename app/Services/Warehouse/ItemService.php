<?php

namespace App\Services\Warehouse;

use App\Models\Item;
use Carbon\Carbon;
use App\Models\Unit;
use App\Models\Section;
use App\Enums\ShipmentStatus;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class ItemService
{



 public function store(array $data)
{
    return DB::transaction(function () use ($data) {

        /*
        |--------------------------------------------------------------------------
        | إنشاء المادة
        |--------------------------------------------------------------------------
        */
        $item = Item::create([

            'name' => $data['name'],

            'section_id' => $data['section_id'],

   'image'=>$data['image'] ?? null,

            'unit_id' => $data['unit_id'],
            
 
            'is_raw_material' => $data['is_raw_material'],

            'minimum_quantity' => $data['minimum_quantity'] ?? 0,
        ]);

        /*
        |--------------------------------------------------------------------------
        | رفع الصورة
        |--------------------------------------------------------------------------
        */
        if (!empty($data['image'])) {

            $item->addMedia($data['image'])
                ->toMediaCollection('items');
        }

        /*
        |--------------------------------------------------------------------------
        | إذا مادة أولية → إنشاء دفعة
        |--------------------------------------------------------------------------
        */
        if ($item->is_raw_material) {

            ShipmentItem::create([

                'shipment_id' => null,

                'item_id' => $item->id,

                'quantity_required' => 0,

                'quantity_received' =>
                    $data['quantity_received'] ?? 0,

                'price' =>
                    $data['price'] ?? null,

                'expiry_date' =>
                    $data['expiry_date'] ?? null,
            ]);
        }

        $item->refresh();

        /*
        |--------------------------------------------------------------------------
        | البيانات النهائية
        |--------------------------------------------------------------------------
        */
        return [

            'id' => $item->id,

            'name' => $item->name,

            'image' =>
                $item->getFirstMediaUrl('items'),

            'is_raw_material' =>
                $item->is_raw_material,

            'section_id' =>
                $item->section->id ?? null,

            'section_name' =>
                $item->section->name ?? null,

            'unit_id' =>
                $item->unit->id ?? null,

            'unit_name' =>
                $item->unit->name ?? null,

            'minimum_quantity' =>
                $item->minimum_quantity,

         'quantity_received' =>
                    $data['quantity_received'] ?? 0,

                'price' =>
                    $data['price'] ?? null,

                'expiry_date' =>
                    $data['expiry_date'] ?? null,
        ];
    });
}





//





   public function show(Item $item): array
{
    $item->load([
        'section',
        'unit',
        'shipmentItems.shipment'
    ]);

    
    $batches = ShipmentItem::with('shipment')
        ->where('item_id', $item->id)
        ->where('quantity_received', '>', 0)
        ->whereHas('shipment', function ($q) {

            $q->whereIn('status', [

                ShipmentStatus::APPROVED_LAB->value,
                ShipmentStatus::FINISHED->value,
            ]);
        })
        ->get();

    /*
    |--------------------------------------------------------------------------
    | تنسيق الدفعة
    |--------------------------------------------------------------------------
    */
    $formattedBatches = $batches->map(function ($batch) {

        $status = 'valid';

        if ($batch->expiry_date) {

            $expiry = Carbon::parse($batch->expiry_date);

            if ($expiry->lt(now())) {

                $status = 'expired';

            } elseif ($expiry->between(now(), now()->addDays(10))) {

                $status = 'near_expiry';
            }
        }

        return [

            'batch_id' => $batch->id,

            'shipment_id' => $batch->shipment_id,

            'shipment_name' =>
                $batch->shipment->supplier ?? null,

            'quantity_received' =>
                $batch->quantity_received,

            'expiry_date' =>
                $batch->expiry_date->format('Y-m-d') ?? null,

            'received_at' =>
                $batch->created_at
                    ? $batch->created_at->format('Y-m-d')
                    : null,

            'status' => $status,
        ];
    });

    return [

        'id' => $item->id,

        'name' => $item->name,

        'image' => $item->image,

        'is_raw_material' =>
            $item->is_raw_material,

        'section_id' =>
            $item->section->id ?? null,

        'section_name' =>
            $item->section->ar_name ?? null,

        'unit_id' =>
            $item->unit->id ?? null,

        'unit_name' =>
            $item->unit->name ?? null,

        'bom' => collect($item->bom)->map(function ($bom) {

            return [

                'id' => $bom['id'] ?? null,

                'item_id' => $bom['item_id'] ?? null,

                'item_name' => $bom['item_name'] ?? null,

                'unit' => $bom['unit'] ?? null,

                'quantity' => $bom['quantity'] ?? null,
            ];
        }),

       
        'batches' => $formattedBatches->values(),
    ];
}
    

public function index(): array
{
    $items = Item::with([
        'section',
        'unit',
        'shipmentItems.shipment'
    ])->get();

    return $items->map(function ($item) {

        /*
        |--------------------------------------------------------------------------
        | فقط الدفعات المقبولة
        |--------------------------------------------------------------------------
        */
        $approvedBatches = ShipmentItem::with('shipment')
            ->where('item_id', $item->id)
            ->where('quantity_received', '>', 0)
            ->whereHas('shipment', function ($q) {

                $q->whereIn('status', [

                    ShipmentStatus::APPROVED_LAB->value,
                    ShipmentStatus::FINISHED->value,
                ]);
            })
            ->get();

        /*
        |--------------------------------------------------------------------------
        | دفعات منتهية
        |--------------------------------------------------------------------------
        */
        $expiredBatches = $approvedBatches
            ->filter(function ($batch) {

                return $batch->expiry_date
                    && Carbon::parse(
                        $batch->expiry_date
                    )->lt(now());
            });

        /*
        |--------------------------------------------------------------------------
        | دفعات قريبة للانتهاء
        |--------------------------------------------------------------------------
        */
        $nearExpiryBatches = $approvedBatches
            ->filter(function ($batch) {

                if (!$batch->expiry_date) {
                    return false;
                }

                $expiry = Carbon::parse(
                    $batch->expiry_date
                );

                return $expiry->between(
                    now(),
                    now()->addDays(10)
                );
            });

        /*
        |--------------------------------------------------------------------------
        | دفعات سليمة
        |--------------------------------------------------------------------------
        */
        $validBatches = $approvedBatches
            ->filter(function ($batch) {

                if (!$batch->expiry_date) {
                    return true;
                }

                return Carbon::parse(
                    $batch->expiry_date
                )->gt(now()->addDays(10));
            });

        return [

            /*
            |--------------------------------------------------------------------------
            | بيانات المادة
            |--------------------------------------------------------------------------
            */
            'id' => $item->id,

            'name' => $item->name,

            'image' => $item->image,

            'is_raw_material' =>
                $item->is_raw_material,

            'section_id' =>
                $item->section->id ?? null,

            'section_name' =>
                $item->section->ar_name ?? null,

            'unit_id' =>
                $item->unit->id ?? null,

            'unit_name' =>
                $item->unit->name ?? null,

          'statistics' => [
'required_materials_quantity' =>
    collect($item->bom)->sum('quantity'),
    'total_stock' =>
        $validBatches->sum('quantity_received'),

    'approved_batches_count' =>
        $approvedBatches->count(),

    'expired_batches_count' =>
        $expiredBatches->count(),

    'near_expiry_batches_count' =>
        $nearExpiryBatches->count(),

    'valid_batches_count' =>
        $validBatches->count(),
],
        ];

    })->values()->toArray();
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

   

public function sections()
{
    return Section::select(
        'id',
        'ar_name',
        'en_name'
    )->get();
}



public function units()
{
    return Unit::select(
        'id',
        'name'
    )->get();
}
}
