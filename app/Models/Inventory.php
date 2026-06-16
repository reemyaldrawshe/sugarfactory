<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }
}
