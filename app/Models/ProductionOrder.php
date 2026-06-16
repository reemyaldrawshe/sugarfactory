<?php

namespace App\Models;
use App\Models\ProductionOrderLog;
use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];


    // 🔗 المنتج النهائي
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(User::class, 'warehouse_id');
    }

    public function production()
    {
        return $this->belongsTo(User::class, 'production_id');
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
