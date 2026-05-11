<?php

namespace App\Models;
use App\Models\ProductionOrderLog;
use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $fillable = [
        'item_id',
        'quantity',
        'produced_quantity',
        'status',
    
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

   public function logs()
{
    return $this->hasMany(
        ProductionOrderLog::class
    );
}

    // 🔗 المنشئ
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}