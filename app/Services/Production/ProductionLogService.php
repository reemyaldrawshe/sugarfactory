<?php

namespace App\Services\Production;

use App\Models\ProductionOrderLog;

class ProductionLogService
{
    public function log($order, $action)
    {
        ProductionOrderLog::create([
            'production_order_id' => $order->id,
            'user_id' => auth()->id(),
            'action' => $action,
        ]);
    }
}
