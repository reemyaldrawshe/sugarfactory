<?php

namespace App\Services\Admin;

use App\Models\Item;
use App\Models\Shipment;
use App\Models\ProductionOrder;
use App\Enums\ProductionStatusEnum;
use App\Enums\ShipmentStatus;
use Carbon\Carbon;

class DashboardService
{
    public function getStats(): array
    {
        $now = Carbon::today();
        $oneMonthLater = Carbon::today()->addMonth();

        return [
            /*
            |----------------------------------
            | 1. Active Production Orders
            |----------------------------------
            */
            'active_production_orders' => ProductionOrder::whereIn('status', [
                ProductionStatusEnum::MATERIALS_RESERVED->value,
                ProductionStatusEnum::SENT_TO_PRODUCTION->value,
                ProductionStatusEnum::IN_PRODUCTION->value,
                ProductionStatusEnum::PAUSED->value,
            ])->count(),

            /*
            |----------------------------------
            | 2. Close to Expiration Shipments
            | (has at least one item expiring within 1 month)
            |----------------------------------
            */
            'close_to_expiration_shipments' => Shipment::whereHas('items', function ($q) use ($now, $oneMonthLater) {
                $q->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [$now, $oneMonthLater]);
            })->count(),

            /*
            |----------------------------------
            | 3. Expired Shipments
            | (has at least one expired item)
            |----------------------------------
            */
            'expired_shipments' => Shipment::whereHas('items', function ($q) use ($now) {
                $q->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<=', $now);
            })->count(),

            /*
            |----------------------------------
            | 4. Number of Unique Items
            |----------------------------------
            */
            'unique_items' => Item::count(),

            /*
            |----------------------------------
            | 5. Pending Lab Shipments
            |----------------------------------
            */
            'pending_lab_shipments' => Shipment::where('status', ShipmentStatus::PENDING_LAB->value)
                ->count(),

            /*
            |----------------------------------
            | 6. Pending Purchase Shipments
            |----------------------------------
            */
            'pending_purchase_shipments' => Shipment::where('status', ShipmentStatus::PENDING_PURCHASE->value)
                ->count(),
        ];
    }
}
