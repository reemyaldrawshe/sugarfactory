<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Item extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

   // protected $appends = ['quantity', 'bom'];

    protected $hidden = ['media'];
    protected $appends = ['quantity','reserved_quantity', 'bom', 'section_name', 'unit_name', 'expired_count', 'expiring_soon_count','good_count','total_batches_count','image' ];
    
    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    // 2. دالة حساب مجموع الكميات المحجوزة من جميع الدفعات
    public function getReservedQuantityAttribute(): int
    {
       // return (int) $this->shipmentItems()->sum('quantity_reserved');
       return (int) $this->shipmentItems()
            ->where('quantity_received', '>', 0)
            ->sum('quantity_reserved');
    }
// اسم القسم
    public function getSectionNameAttribute() {
        return $this->section->name ?? null;
    }

    // اسم الوحدة
    public function getUnitNameAttribute() {
        return $this->unit->name ?? null;
    }
public function getTotalBatchesCountAttribute() {
    // نستخدم -> (بدون أقواس) لأننا نريد العدد من المجموعة المحملة مسبقاً (Eager Loading)
    // هذا أسرع بكثير من عمل استعلام جديد لقاعدة البيانات
    return $this->shipmentItems->where('quantity_received', '>', 0)
->count();
}
    // عدد الدفعات منتهية الصلاحية
    public function getExpiredCountAttribute() {
        return $this->shipmentItems()
            ->where('quantity_received', '>', 0)
            ->where('expiry_date', '<', Carbon::now())
            ->count();
    }
public function getGoodCountAttribute() {
    return $this->shipmentItems()
                ->where('quantity_received', '>', 0)
        ->where('expiry_date', '>', Carbon::now()->addMonth())
        ->count();
}
    // عدد الدفعات التي ستنتهي خلال شهر
    public function getExpiringSoonCountAttribute() {
        return $this->shipmentItems()
                    ->where('quantity_received', '>', 0)
            ->where('expiry_date', '>=', Carbon::now())
            ->where('expiry_date', '<=', Carbon::now()->addMonth())
            ->count();
    }
    //todo subtract used & wasted quantities in the future
    public function getQuantityAttribute(): int
    {
        return (int) $this->shipmentItems()->sum('quantity_received');
    }

   public function getBomAttribute(): array
{
    return BOM::with('basicItem.unit')
        ->where('final_item_id', $this->id)
        ->get()
        ->map(function ($bom) {

            return [

                'id' => $bom->id,

                'item_id' => $bom->basic_item_id,
                'final_item_quantity' => $bom->final_item_quantity,
                'item_name' => $bom->basicItem->name ?? null,
                'is_primary' => $bom->is_primary??null,
                'unit' => $bom->basicItem->unit->name ?? null,
                'quantity' => $bom->basic_item_quantity,
                'image' => $bom->basicItem->image ?? null,
            ];
        })
        ->values()
        ->toArray();
}
// جلب رابط الصورة ديناميكياً باستخدام مكتبة Spatie
    public function getImageAttribute() 
    {
        $url = $this->getFirstMediaUrl('item_image');
        return $url ?: null; // إذا لم يكن هناك صورة يرجع null
    }
    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function bomAsFinal(): HasMany
    {
        return $this->hasMany(BOM::class, 'final_item_id');
    }
    public function productionOrders()
{
    return $this->hasMany(ProductionOrder::class);
}

}
