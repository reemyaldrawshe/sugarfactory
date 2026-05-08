<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionOrderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'user_id',
        'action',
        'quantity',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /*
    =========================
    العلاقات
    =========================
    */

    public function order()
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    =========================
    ثوابت الأكشن (مهم جداً)
    =========================
    */
    
}