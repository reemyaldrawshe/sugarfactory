<?php

namespace App\Services\Production\Inventory;

use App\Models\Item;
use App\Models\ShipmentItem;
use App\Enums\ShipmentStatus;

class InventoryAuditService
{
    public function getItemSystemData($itemId)
    {
        $purchased = ShipmentItem::where('item_id', $itemId)
            ->whereHas('shipment', function ($q) {
                $q->where('status', '!=', ShipmentStatus::REJECTED_LAB->value);
            })
            ->sum('quantity_received');

        $used = ShipmentItem::where('item_id', $itemId)
            ->sum('quantity_received'); // FIFO deducted

        $actualSystemStock = $used;

        return [
            'purchased_quantity' => $purchased,
            'system_actual_quantity' => $actualSystemStock,
        ];
    }
}
