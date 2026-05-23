<?php

namespace App\Services\Production\Inventory;

use App\Models\ShipmentItem;
use App\Models\ProductionOrderMaterial;
use App\Enums\ShipmentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /*
    |--------------------------------------------------------------------------
    | Check Availability
    |--------------------------------------------------------------------------
    */

    public function checkAvailability($itemId, $requiredQty)
    {
        $available = ShipmentItem::where('item_id', $itemId)

            ->where('quantity_received', '>', 0)

            ->whereHas('shipment', function ($q) {
                $q->where(
                    'status',
                    ShipmentStatus::APPROVED_LAB->value
                );
            })

            ->sum('quantity_received');

        return $available >= $requiredQty;
    }

    /*
    |--------------------------------------------------------------------------
    | FIFO Reservation
    |--------------------------------------------------------------------------
    */

    public function reserveFIFO(
        $productionOrder,
        $itemId,
        $requiredQty
    ) {

        $remaining = $requiredQty;

        $batches = ShipmentItem::where('item_id', $itemId)

            ->where('quantity_received', '>', 0)

            ->whereHas('shipment', function ($q) {
                $q->where(
                    'status',
                    ShipmentStatus::APPROVED_LAB->value
                );
            })

            ->orderBy('expiry_date')
            ->orderBy('created_at')
            ->get();

        foreach ($batches as $batch) {

            if ($remaining <= 0) {
                break;
            }

            $available = $batch->quantity_received;

            $deduct = min($available, $remaining);

            if ($deduct > 0) {

                $batch->decrement(
                    'quantity_received',
                    $deduct
                );

                ProductionOrderMaterial::create([

                    'production_order_id' =>
                        $productionOrder->id,

                    'item_id' =>
                        $itemId,

                    'shipment_item_id' =>
                        $batch->id,

                    'required_quantity' =>
                        $requiredQty,

                    'consumed_quantity' =>
                        $deduct,
                ]);

                $remaining -= $deduct;
            }
        }

        if ($remaining > 0) {

            throw ValidationException::withMessages([
                'stock' => 'Insufficient stock'
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Increase Finished Product Stock
    |--------------------------------------------------------------------------
    */

    public function increaseFinishedStock($itemId, $qty)
    {
        ShipmentItem::updateOrCreate(
            ['item_id' => $itemId],
            [
                'quantity_received' =>
                    DB::raw("quantity_received + $qty")
            ]
        );
    }
}
