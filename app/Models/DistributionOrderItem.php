<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionOrderItem extends Model
{
    protected $fillable = [
        'distribution_order_id',
        'item_id',
        'quantity',
        'price_per_ton',
        'total_price'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DistributionOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DistributionOrderMaterial::class, 'distribution_order_item_id');
    }
}