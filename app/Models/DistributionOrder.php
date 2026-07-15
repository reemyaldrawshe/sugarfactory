<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionOrder extends Model
{
    protected $fillable = [
        'user_id',
        'customer_name',
        'status',
        'notes',
        'approved_at',
        'dispatched_at',
        'sold_at'
    ];

    // علاقة جلب المواد التابعة لهذا الطلب
    public function items(): HasMany
    {
        return $table = $this->hasMany(DistributionOrderItem::class);
    }

    // علاقة جلب تفاصيل دفعات المستودع المحجوزة
    public function batchAllocations(): HasMany
    {
        return $this->hasMany(DistributionOrderMaterial::class);
    }

    // المستخدم المنشئ للطلب
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}