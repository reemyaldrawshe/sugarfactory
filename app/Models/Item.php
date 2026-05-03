<?php

namespace App\Models;

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

    protected $appends = ['quantity',  'bom'];

    protected $hidden = ['media'];
    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    //todo subtract used & wasted quantities in the future
    public function getQuantityAttribute(): int
    {
        return (int) $this->shipmentItems()->sum('quantity_received');
    }

    public function getBomAttribute(): array
    {
        return $this->bomAsFinal()
            ->with(['basicItem.unit'])
            ->get()
            ->map(function ($bom) {
                return [
                    'id' => $bom->id,
                    'item_id' => $bom->basicItem->id,
                    'item_name' => $bom->basicItem->name,
                    'unit' => $bom->basicItem->unit->name ?? null,
                    'quantity' => $bom->basic_item_quantity,
                ];
            })
            ->toArray();
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
}
