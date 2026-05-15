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

    protected $casts = [
        'price_history' => 'array',
        'quantity_history' => 'array',
        'expiry_date' => 'date',
    ];

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
}
