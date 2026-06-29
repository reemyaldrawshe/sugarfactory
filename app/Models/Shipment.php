<?php
// app/Models/Shipment.php
namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'received_at' => 'date',
        'status' => ShipmentStatus::class,
        'admin_approved_at' => 'datetime',
        'paid_at' => 'datetime',

        'invoice_images' => 'array', // 💡 التعديل الجديد هنا: لتحويل حقل الصور إلى مصفوفة تلقائياً
        'purchase_updated_at' => 'datetime',
        'warehouse_confirmed_at' => 'datetime',
        'sent_to_lab_at' => 'datetime',
        'lab_approved_at' => 'datetime',
        'final_confirmed_at' => 'datetime',
    ];

    // protected $appends = [
    //     'total_price',
    // ];

    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_id');
    }

    public function adminApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approved_by');
    }
     public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function purchaseUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchase_updated_by');
    }

    public function warehouseConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_confirmed_by');
    }

    public function sentToLabBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_to_lab_by');
    }

    public function labApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lab_approved_by');
    }

    public function finalConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_confirmed_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    // Scopes
    public function scopePendingAdmin($query)
    {
        return $query->where('status', ShipmentStatus::PENDING_ADMIN);
    }

    public function scopePendingPurchase($query)
    {
        return $query->where('status', ShipmentStatus::PENDING_PURCHASE);
    }

    public function scopePendingLab($query)
    {
        return $query->where('status', ShipmentStatus::PENDING_LAB);
    }
public function scopePendingPayment($query)
{
    return $query->where('status', ShipmentStatus::FINISHED);
}

public function scopePaid($query)
{
    return $query->where('status', ShipmentStatus::PAID);
}
    // Helper Methods
    public function canTransitionTo(ShipmentStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function recordStatusChange(ShipmentStatus $oldStatus, User $changedBy, ?string $reason = null, array $metadata = []): void
    {
        ShipmentStatusHistory::create([
            'shipment_id' => $this->id,
            'old_status' => $oldStatus->value,
            'new_status' => $this->status->value,
            'changed_by' => $changedBy->id,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    // public function getTotalPriceAttribute(): float
    // {
    //     return (float) $this->items()
    //         ->selectRaw('SUM(quantity_received * price) as total')
    //         ->value('total');
    // }
}
