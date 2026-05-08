<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $fillable = [
        'item_id',
        'quantity',
        'produced_quantity',
        'status',
        // 'created_by',
        // 'manager_approved_by',
        // 'manager_approved_at',
        // 'warehouse_approved_by',
        // 'warehouse_approved_at',
        // 'started_at',
        // 'paused_at',
        // 'resumed_at',
        // 'completed_at',
        'notes',
    ];

    protected $hidden = [
        'updated_at',
        'created_at',
    ];


    // 🔗 المنتج النهائي
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // 🔗 المواد
    public function materials()
    {
        return $this->hasMany(ProductionOrderMaterial::class);
    }

    // 🔗 السجل
    public function histories()
    {
        return $this->hasMany(ProductionOrderHistory::class);
    }

    // 🔗 المنشئ
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}