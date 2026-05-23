<?php

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Services\Production\Inventory\InventoryService;
use App\Enums\ProductionStatusEnum;
use Illuminate\Validation\ValidationException;

class ProductionExecutionService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected ProductionLogService $logService
    ) {}

    public function start($id)
    {
        $order = ProductionOrder::findOrFail($id);

        if (
            $order->status !==
            ProductionStatusEnum::SENT_TO_PRODUCTION->value
        ) {

            throw ValidationException::withMessages([
                'status' => 'Invalid status'
            ]);
        }

        $order->update([
            'status' =>
                ProductionStatusEnum
                ::IN_PRODUCTION
                    ->value,

            'started_at' => now(),
        ]);

        return $order;
    }

    public function pause($id)
    {
        $order = ProductionOrder::findOrFail($id);

        $order->update([
            'status' =>
                ProductionStatusEnum::PAUSED->value
        ]);

        return $order;
    }

    public function resume($id)
    {
        $order = ProductionOrder::findOrFail($id);

        $order->update([
            'status' =>
                ProductionStatusEnum
                ::IN_PRODUCTION
                    ->value
        ]);

        return $order;
    }

    public function complete($id, $producedQty)
    {
        $order = ProductionOrder::findOrFail($id);

        $remaining =
            $order->quantity
            - $order->produced_quantity;

        if ($producedQty > $remaining) {

            throw ValidationException::withMessages([
                'qty' => 'Exceeded remaining quantity'
            ]);
        }

        $order->increment(
            'produced_quantity',
            $producedQty
        );

        $this->inventoryService
            ->increaseFinishedStock(
                $order->item_id,
                $producedQty
            );

        $order->refresh();


        $order->update([
            'status' =>
                ProductionStatusEnum
                ::COMPLETED
                    ->value,

        ]);

        return $order;
    }
}
