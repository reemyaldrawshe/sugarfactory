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
        //'status' => \App\Enums\ProductionStatusEnum::class, // من الأفضل ربط الـ Enum هنا
    ];
// جلب طلبات الإنتاج فقط
    public function scopeProduction($query)
    {
        return $query->where('type', 'production');
    }

    // جلب طلبات المبيعات فقط
    public function scopeSales($query)
    {
        return $query->where('type', 'sales');
    }

    public function isSales(): bool
    {
        return $this->type === 'sales';
    }

    public function isProduction(): bool
    {
        return $this->type === 'production';
    }

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
    // 💡 العلاقة الجديدة: مندوب المبيعات
    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_id');
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
