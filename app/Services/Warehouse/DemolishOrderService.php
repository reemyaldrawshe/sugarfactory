<?php

namespace App\Services\Warehouse;

use App\Models\DemolishOrder;
use App\Models\Item;
use App\Services\ItemTrackingService;
use App\Services\Production\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DemolishOrderService
{
    public function __construct(
        protected InventoryService    $inventoryService,
        protected ItemTrackingService $trackingService
    ){}

    /**
     * Create a new demolish order
     */
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Check if item exists
            $item = Item::findOrFail($data['item_id']);

            // Check if sufficient quantity in inventory
            if (!$this->inventoryService->checkAvailability($data['item_id'], $data['quantity'])) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient quantity in inventory'
                ]);
            }

            // Create demolish order
            $demolishOrder = DemolishOrder::create([
                'section_id' => $data['section_id'],
                'item_id' => $data['item_id'],
                'shipment_id' => $data['shipment_id'] ?? null,
                'quantity' => $data['quantity'],
                'reason' => $data['reason'],
                'status' => 'pending',
                'created_by' => Auth::id()
            ]);

            $this->trackingService->logDemolish(
                $demolishOrder,
                $item,
                $data['quantity'],
                auth()->user(),
                "Demolished #{$demolishOrder->id}"
            );

            // Handle images
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $image) {
                    $demolishOrder->addMedia($image)
                        ->toMediaCollection('demolish_images');
                }
            }

            return $demolishOrder->load(['item', 'creator', 'media']);
        });
    }

    /**
     * Update demolish order
     */
    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $demolishOrder = DemolishOrder::findOrFail($id);

            if ($demolishOrder->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Cannot update approved or completed demolish order'
                ]);
            }

            $demolishOrder->update([
                'section_id' => $data['section_id'] ?? $demolishOrder->section_id,
                'item_id' => $data['item_id'] ?? $demolishOrder->item_id,
                'shipment_id' => $data['shipment_id'] ?? $demolishOrder->shipment_id,
                'quantity' => $data['quantity'] ?? $demolishOrder->quantity,
                'reason' => $data['reason'] ?? $demolishOrder->reason,
            ]);

            // Update images if provided
            if (isset($data['images']) && is_array($data['images'])) {
                $demolishOrder->clearMediaCollection('demolish_images');
                foreach ($data['images'] as $image) {
                    $demolishOrder->addMedia($image)
                        ->toMediaCollection('demolish_images');
                }
            }

            return $demolishOrder->load(['item', 'media']);
        });
    }

    /**
     * Delete demolish order (only if pending)
     */
    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $demolishOrder = DemolishOrder::findOrFail($id);

            if ($demolishOrder->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Cannot delete processed order'
                ]);
            }

            $demolishOrder->clearMediaCollection('demolish_images');
            $demolishOrder->delete();

            return true;
        });
    }

    /**
     * Get demolish orders with filters
     */
    public function getDemolishOrders(array $filters = [], $perPage = 50)
    {
        $query = DemolishOrder::with(['item', 'shipment', 'creator', 'approver', 'media'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['section_id'])) {
            $query->where('section_id', $filters['section_id']);
        }

        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return [
            'orders' => $query->paginate($perPage),
            'filters' => $filters,
            'total' => $query->count()
        ];
    }

    /**
     * Get single demolish order
     */
    public function getDemolishOrder($id)
    {
        return DemolishOrder::with(['item', 'shipment', 'creator', 'approver', 'media'])
            ->findOrFail($id);
    }

    /**
     * Get demolish statistics
     */
    public function getDemolishStatistics()
    {
        return [
            'total_orders' => DemolishOrder::count(),
            'by_status' => [
                'pending' => DemolishOrder::where('status', 'pending')->count(),
                'approved' => DemolishOrder::where('status', 'approved')->count(),
                'rejected' => DemolishOrder::where('status', 'rejected')->count(),
                'completed' => DemolishOrder::where('status', 'completed')->count(),
            ],
            'total_quantity_demolished' => DemolishOrder::where('status', 'approved')->sum('quantity'),
            'by_section' => DemolishOrder::select('section_id', DB::raw('count(*) as total'))
                ->groupBy('section_id')
                ->get(),
            'today_orders' => DemolishOrder::whereDate('created_at', today())->count(),
            'this_month_orders' => DemolishOrder::whereMonth('created_at', now()->month)->count(),
            'top_items' => DemolishOrder::select('item_id', DB::raw('count(*) as total_orders'), DB::raw('sum(quantity) as total_quantity'))
                ->with('item')
                ->groupBy('item_id')
                ->orderBy('total_quantity', 'desc')
                ->limit(10)
                ->get()
        ];
    }
}
