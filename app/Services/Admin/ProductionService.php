<?php

namespace App\Services\Admin;

use App\Models\ProductionOrder;
use App\Enums\ProductionStatusEnum;
use App\Enums\ProductionLogAction;
use App\Services\Production\ProductionLogService;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function __construct(
        protected ProductionLogService $logService
    ) {}

    public function approve($id)
    {
        $order = ProductionOrder::findOrFail($id);

        if (
            $order->status !==
            ProductionStatusEnum::PENDING->value
        ) {

            throw ValidationException::withMessages([
                'status' => 'Invalid status'
            ]);
        }

        $order->update([
            'status' =>
                ProductionStatusEnum
                ::APPROVED_BY_MANAGER
                    ->value
        ]);

        $this->logService->log(
            $order,
            ProductionLogAction::MANAGER_APPROVED->value
        );

        return $order;
    }

    public function reject($id)
    {
        $order = ProductionOrder::findOrFail($id);

        if (
            $order->status !==
            ProductionStatusEnum::PENDING->value
        ) {

            throw ValidationException::withMessages([
                'status' => 'Invalid status'
            ]);
        }

        $order->update([
            'status' =>
                ProductionStatusEnum
                ::REJECTED_BY_MANAGER
                    ->value
        ]);

        $this->logService->log(
            $order,
            ProductionLogAction::MANAGER_REJECTED->value
        );

        return $order;
    }
}
