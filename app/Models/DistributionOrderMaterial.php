<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionOrderMaterial extends Model
{
    protected $fillable = [
        'distribution_order_id',
        'distribution_order_item_id',
        'item_id',
        'shipment_item_id',
        'allocated_quantity'
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function shipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class);
    }
}