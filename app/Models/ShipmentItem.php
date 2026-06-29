<?php
// app/Models/ShipmentItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ShipmentItem extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];
// 1. أضفنا الحقل الوهمي ليتم تضمينه تلقائياً في الـ JSON المعاد للتطبيق
    protected $appends = [
        'expiry_status',
        'total_price',
        'unit_price',

    ];
    protected $with=['shipment'];
    protected $casts = [
        'price_history' => 'array',
        'quantity_history' => 'array',
        'expiry_date' => 'date',

    ];
    // 2. دالة الـ Accessor لحساب حالة الصلاحية ديناميكياً
    public function getExpiryStatusAttribute(): string
    {
        if (!$this->expiry_date) {
            return 'no_expiry';
        }

        // بما أن الحقل مضاف للـ casts كـ date، فهو كائن Carbon جاهز
        $expiry = $this->expiry_date;
        $now = now()->startOfDay();
        $oneMonthFromNow = now()->addMonth()->endOfDay();

        if ($expiry->isPast()) {
            return 'expired'; // منتهية الصلاحية
        }

        if ($expiry->lessThanOrEqualTo($oneMonthFromNow)) {
            return 'expiring_soon'; // ستنتهي خلال شهر أو أقل
        }

        return 'good'; // صالحة وممتازة
    }

    public function shipment()
{
    return $this->belongsTo(
        Shipment::class,
        'shipment_id'
    );
}

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
// 💡 إضافة دالة الـ Accessor لحساب سعر الوحدة تلقائياً إذا لم يكن مخزناً
public function getUnitPriceAttribute(): float
{
    // إذا كان الحقل موجوداً في قاعدة البيانات، أرجعه
    if (isset($this->attributes['unit_price']) && $this->attributes['unit_price'] > 0) {
        return (float) $this->attributes['unit_price'];
    }

    // إذا لم يكن موجوداً، احسبه بناءً على السعر الكلي والكمية
    return ($this->quantity_received > 0) 
        ? (float) ($this->price / $this->quantity_received) 
        : 0;
}
    public function updatePrice(float $newPrice, User $updatedBy): void
    {
        $history = $this->price_history ?? [];
        $history[] = [
            'old_price' => $this->price,
            'new_price' => $newPrice,
            'updated_by' => $updatedBy->id,
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->update([
            'price' => $newPrice,
            'price_history' => $history,
        ]);
    }

    public function updateQuantity(int $newQuantity, User $updatedBy): void
    {
        $history = $this->quantity_history ?? [];
        $history[] = [
            'old_quantity' => $this->quantity_received,
            'new_quantity' => $newQuantity,
            'updated_by' => $updatedBy->id,
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->update([
            'quantity_received' => $newQuantity,
            'quantity_history' => $history,
        ]);
    }

    public function getTotalPriceAttribute(): float
    {
        return (float) ($this->quantity_received * $this->price);
    }
}
