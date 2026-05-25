<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DemolishOrder extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'approved_at' => 'datetime',
        'section_id' => 'integer',
        'item_id' => 'integer',
        'shipment_id' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('demolish_images')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/gif']);
    }
}
