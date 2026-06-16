<?php

namespace App\Services\Production\Inventory;

use App\Models\Inventory;
use App\Models\InventoryItem;
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
    public function __construct(private readonly InventoryAuditService $auditService){}

    public function createInventory($request)
    {
        return DB::transaction(function () use ($request) {
            $inventory = Inventory::create([
                'name' => $request['name'],
                'date' => now(),
                'status' => 'pending',
                'overall_compatibility' => 0,
            ]);

            $totalCompatibility = 0;
            $count = 0;

            foreach ($request['items'] as $row) {

                $itemId = $row['item_id'];
                $actual = $row['actual_quantity'];

                $system = $this->auditService->getItemSystemData($itemId);

                $purchased = $system['purchased_quantity'];

                $compatibility = $purchased > 0
                    ? ($actual / $purchased) * 100
                    : 0;

                $difference = $actual - $system['system_actual_quantity'];

                InventoryItem::create([
                    'inventory_id' => $inventory->id,
                    'item_id' => $itemId,
                    'purchased_quantity' => $purchased,
                    'system_quantity' => $system['system_actual_quantity'],
                    'actual_quantity' => $actual,
                    'difference' => $difference,
                    'compatibility_percent' => round($compatibility, 2),
                    'notes' => $this->generateNote($compatibility),
                ]);

                $totalCompatibility += $compatibility;
                $count++;
            }

            $inventory->update([
                'overall_compatibility' =>
                    $count > 0 ? round($totalCompatibility / $count, 2) : 0
            ]);

            return $inventory->load('items.item');
        });
    }
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

    private function generateNote($compatibility)
    {
        return match (true) {
            $compatibility >= 98 => 'Excellent match',
            $compatibility >= 90 => 'Good match',
            $compatibility >= 70 => 'Minor discrepancy',
            default => 'Major discrepancy',
        };
    }
}
