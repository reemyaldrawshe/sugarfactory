<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BOM extends Model
{
    protected $guarded = [];

    public function basicItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'basic_item_id');
    }

    public function finalItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'final_item_id');
    }
}
