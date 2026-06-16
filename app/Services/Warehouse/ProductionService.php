<?php

namespace App\Services\Warehouse;

use App\Models\ProductionOrder;
use App\Models\BOM;
use App\Services\ItemTrackingService;
use App\Services\Production\Inventory\InventoryService;
use App\Enums\ProductionStatusEnum;
use App\Services\Production\ProductionLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    protected $trackingService;

// Update constructor
    public function __construct(
        InventoryService $inventoryService,
        ProductionLogService $logService,
        ItemTrackingService $trackingService
    ) {
        $this->inventoryService = $inventoryService;
        $this->logService = $logService;
        $this->trackingService = $trackingService;
    }

    public function reserveMaterials($id)
    {
        return DB::transaction(function () use ($id) {

            $order = ProductionOrder::findOrFail($id);

            if (
                $order->status !==
                ProductionStatusEnum::APPROVED_BY_MANAGER->value
            ) {

                throw ValidationException::withMessages([
                    'status' => 'Status should be manager_approved'
                ]);
            }


            $boms = BOM::where(
                'final_item_id',
                $order->item_id
            )->get();

            foreach ($boms as $bom) {

                $required =
                    $bom->basic_item_quantity
                    * $order->quantity;

                if($this->inventoryService->checkAvailability($bom->basic_item_id, $required)){
                    $this->inventoryService->reserveFIFO(
                        $order,
                        $bom->basic_item_id,
                        $required
                    );
                }else{
                    throw ValidationException::withMessages([
                        'status' => 'quantity not efficient'
                    ]);
                }

            }

            $order->update([
                'warehouse_id' => auth()->id(),
                'status' =>
                    ProductionStatusEnum
                    ::MATERIALS_RESERVED
                        ->value,
            ]);

            return $order;
        });
    }

    public function sendToProduction($id)
    {
        $order = ProductionOrder::findOrFail($id);

        if (
            $order->status !==
            ProductionStatusEnum::MATERIALS_RESERVED->value
        ) {
            throw ValidationException::withMessages([
                'status' => 'status should be materials_reserved'
            ]);
        }

        $order->update([
            'status' => ProductionStatusEnum::SENT_TO_PRODUCTION->value
        ]);

        // Add tracking log for each reserved material
        foreach ($order->reservedMaterials as $reserved) {
            $this->trackingService->logProductionIssue(
                $order,
                $reserved->item,
                $reserved->quantity,
                auth()->user(),
                "Materials issued for production order #{$order->id}"
            );
        }

        return $order;
    }

    public function checkAvailability($id)
    {

    }
}
