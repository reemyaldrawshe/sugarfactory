<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ItemTrackingLog extends Model
{
    protected $table = 'item_tracking_logs';

    protected $fillable = [
        'type',
        'trackable_id',
        'trackable_type',
        'status',
        'item_id',
        'item_name',
        'quantity',
        'shipment_id',
        'sent_from_role',
        'sent_from_user_name',
        'sent_from_user_id',
        'sent_to_role',
        'sent_to_user_name',
        'sent_to_user_id',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
