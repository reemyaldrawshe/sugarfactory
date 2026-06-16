<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $guarded = [];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
