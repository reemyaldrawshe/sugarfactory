<?php

namespace App\Services\Production;

use App\Models\Item;
use App\Models\BOM;
use App\Models\ProductionOrder;
use Illuminate\Validation\ValidationException;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProductionLogAction;

class ProductionOrderService
{
    public function __construct(
        protected ProductionLogService $logService
    ) {}

    public function create(array $data)
    {
        $item = Item::findOrFail($data['item_id']);

        if ($item->is_raw_material == 1) {

            throw ValidationException::withMessages([
                'item_id' =>
                    'Cannot produce raw material'
            ]);
        }

        $bomExists = BOM::where(
            'final_item_id',
            $item->id
        )->exists();

        if (!$bomExists) {

            throw ValidationException::withMessages([
                'item_id' =>
                    'Item has no BOM'
            ]);
        }

        $order = ProductionOrder::create([
            'production_id' => auth()->id(),
            'item_id' => $item->id,
            'quantity' => $data['quantity'],
            'status' => ProductionStatusEnum::PENDING->value,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logService->log(
            $order,
            ProductionLogAction::CREATED->value
        );

        return $order;
    }
}
