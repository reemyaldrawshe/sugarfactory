<?php

namespace App\Services\Production;

use App\Models\ProductionOrder;

class ProductionReportService
{
    /*
    |--------------------------------------------------------------------------
    | All Orders
    |--------------------------------------------------------------------------
    */

    public function allOrders()
    {
        return ProductionOrder::with([

            'item',
            'materials.item',
            'materials.shipmentItem',
            'logs.user',
            'item.section',
            'materials.item.section',

        ])
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Material Requests
    |--------------------------------------------------------------------------
    */

    public function materialRequests()
    {
        return [

            'new_requests' => ProductionOrder::with([
                'item.section'
            ])
                ->where(
                    'status',
                    'approved_by_manager'
                )
                ->latest()
                ->get(),

            'preparing' => ProductionOrder::with([
                'item.section',
                'materials.item.section',
                'materials.shipmentItem'
            ])
                ->where(
                    'status',
                    'materials_reserved'
                )
                ->latest()
                ->get(),

            'delivered' => ProductionOrder::with([
                'item.section',
                'materials.item.section',
                'materials.shipmentItem'
            ])
                ->whereIn('status', [
                    'sent_to_production'
                ])
                ->latest()
                ->get(),
        ];
    }
}
