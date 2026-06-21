<?php
// app/Services/ShipmentService.php
namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Http\Requests\Tester\Shipment\LabApproveRequest;
use App\Models\Item;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ShipmentService
{
    protected $trackingService;

    public function __construct(ItemTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * 1. Warehouse creates purchase request
     */
    public function createPurchaseRequest(array $data, $warehouseUser): Shipment
    {
        return DB::transaction(function () use ($data, $warehouseUser) {
            $shipment = Shipment::create([
                'supplier' => $data['supplier'],
                'received_at' => now(),
                'status' => ShipmentStatus::PENDING_ADMIN,
                'warehouse_id' => $warehouseUser->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'item_id' => $item['item_id'],
                    'quantity_required' => $item['quantity_required'],
                    'quantity_received' => 0,
                ]);
            }

            $shipment->recordStatusChange(
                ShipmentStatus::PENDING_ADMIN,
                $warehouseUser,
                'Purchase request created'
            );

            return $shipment->load('items.item.unit');
        });
    }

    /**
     * 2. Admin approves purchase request
     */
    public function adminApprove(int $shipmentId, $adminUser): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $adminUser) {
            $shipment = Shipment::findOrFail($shipmentId);

            if (!$shipment->canTransitionTo(ShipmentStatus::PENDING_PURCHASE)) {
                throw new \Exception('Cannot approve shipment in current status: ' . $shipment->status->label());
            }

            $oldStatus = $shipment->status;
            $shipment->update([
                'status' => ShipmentStatus::PENDING_PURCHASE,
                'admin_approved_by' => $adminUser->id,
                'admin_approved_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $adminUser, 'Admin approved purchase request');

            return $shipment;
        });
    }

    /**
     * 3. Get pending purchase requests for sales/marketing
     */
    public function getPendingPurchaseRequests(array $filters = [])
    {
        return Shipment::with(['items.item.unit', 'warehouse'])
            ->where('status', ShipmentStatus::PENDING_PURCHASE)
            ->when($filters['supplier'] ?? null, fn($q, $s) => $q->where('supplier', 'like', "%$s%"))
            ->when($filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('received_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('received_at', '<=', $d))
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * 4. Sales updates purchase request (prices, quantities, invoice)
     */
    public function updatePurchaseRequest(array $data, $salesUser): Shipment
    {
        return DB::transaction(function () use ($data, $salesUser) {
            $shipment = Shipment::findOrFail($data['shipment_id']);

            if ($shipment->status !== ShipmentStatus::PENDING_PURCHASE) {
                throw new \Exception('Shipment must be in pending purchase status');
            }

            foreach ($data['items'] as $itemData) {
                $shipmentItem = ShipmentItem::where('shipment_id', $shipment->id)
                    ->where('item_id', $itemData['item_id'])
                    ->firstOrFail();

                // Track price changes
                if (isset($itemData['price']) && $itemData['price'] != $shipmentItem->price) {
                    $shipmentItem->updatePrice($itemData['price'], $salesUser);
                }

                // Track quantity changes
                if (isset($itemData['quantity_received']) && $itemData['quantity_received'] != $shipmentItem->quantity_received) {
                    $shipmentItem->updateQuantity($itemData['quantity_received'], $salesUser);
                }
                if(isset($data['invoice_image'])){
                    $media = $shipmentItem->addMediaFromRequest('invoice_image')
                        ->toMediaCollection('invoice_image');
                    $shipmentItem['invoice_image'] = $media->getFullUrl();
                    $shipmentItem->save();
                }
                // Update other fields
                $shipmentItem->update([
                    'expiry_date' => $itemData['expiry_date'] ?? $shipmentItem->expiry_date,
                    'note' => $itemData['note'] ?? $shipmentItem->note,
                ]);
            }

            // Update status to ready at warehouse
            $oldStatus = $shipment->status;
            $shipment->update([
                'status' => ShipmentStatus::READY_AT_WAREHOUSE,
                'purchase_updated_by' => $salesUser->id,
                'purchase_updated_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $salesUser, 'Purchase request updated with prices and quantities');

            return $shipment->load('items.item');
        });
    }

    /**
     * 5. Warehouse confirms receipt before lab testing
     */
    public function warehouseConfirmReceipt(int $shipmentId, $warehouseUser): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $warehouseUser) {
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->status !== ShipmentStatus::READY_AT_WAREHOUSE) {
                throw new \Exception('Shipment must be ready at warehouse for confirmation');
            }

            $oldStatus = $shipment->status;
            $shipment->update([
                'status' => ShipmentStatus::PENDING_LAB,
                'warehouse_confirmed_by' => $warehouseUser->id,
                'warehouse_confirmed_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $warehouseUser, 'Warehouse confirmed receipt before lab testing');

            return $shipment;
        });
    }

    /**
     * 6. Send to lab for testing
     */
    public function sendToLab(int $shipmentId, $warehouseUser): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $warehouseUser) {
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->status !== ShipmentStatus::PENDING_LAB) {
                throw new \Exception('Shipment must be in pending lab status');
            }

            $shipment->update([
                'sent_to_lab_by' => $warehouseUser->id,
                'sent_to_lab_at' => now(),
            ]);

            return $shipment;
        });
    }

    /**
     * 7. Lab uploads test results
     */
    public function labUploadResult(array $data, $testerUser): ShipmentItem
    {
        return DB::transaction(function () use ($data, $testerUser) {
            $item = ShipmentItem::where('shipment_id', $data['shipment_id'])
                ->where('item_id', $data['item_id'])
                ->firstOrFail();

            if(isset($data['lab_test_file'])){
                $media = $item->addMediaFromRequest('lab_test_file')
                    ->toMediaCollection('lab_test_file');
                $item['lab_test_file'] = $media->getFullUrl();
                $item->save();
            }
            $item->update([
                'note' => $data['note'] ?? $item->note,
            ]);

            return $item;
        });
    }

    /**
     * 7b. Lab approves shipment after testing
     */
    public function labApprove(LabApproveRequest $request, int $shipmentId, $testerUser): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $testerUser, $request) {
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->status !== ShipmentStatus::PENDING_LAB) {
                throw new \Exception('Shipment must be in pending lab status');
            }

            $oldStatus = $shipment->status;
            $shipment->update([
                'status' => ShipmentStatus::APPROVED_LAB,
                'lab_approved_by' => $testerUser->id,
                'lab_approved_at' => now(),

            ]);

            // جلب مصفوفة العناصر المرسلة من الواجهة
            $requestItems = $request->input('items');

            // تحديث تاريخ الانتهاء لكل عنصر بشكل منفصل
            foreach ($requestItems as $requestItem) {
                // التأكد من أن هذا العنصر يتبع فعلاً لهذه الشحنة (حماية)
                $shipmentItem = $shipment->items->where('id', $requestItem['shipment_item_id'])->first();
                
                if ($shipmentItem) {
                    $shipmentItem->update([
                        'expiry_date' => $requestItem['expiry_date']
                    ]);
                }
            }

            // foreach ($shipment->items as $item) {
            //     $item['expiry_date'] = $request['expiry_date'] ?? $shipment['expiry_date'];
            //     $item->save();
            // }

            $shipment->recordStatusChange($oldStatus, $testerUser, 'Lab approved shipment');

            return $shipment;
        });
    }

    /**
     * 7c. Lab rejects shipment
     */
    public function labReject(int $shipmentId, $testerUser, string $reason): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $testerUser, $reason) {
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->status !== ShipmentStatus::PENDING_LAB) {
                throw new \Exception('Shipment must be in pending lab status');
            }

            $oldStatus = $shipment->status;
            $shipment->update([
                'status' => ShipmentStatus::REJECTED_LAB,
                'lab_rejection_reason' => $reason,
            ]);

            $shipment->recordStatusChange($oldStatus, $testerUser, "Lab rejected shipment: $reason");

            return $shipment;
        });
    }

    /**
     * 8. Warehouse final confirmation - adds quantities to inventory
     */
    public function finalConfirm(int $shipmentId, $warehouseUser): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $warehouseUser) {
            $shipment = Shipment::findOrFail($shipmentId);

            if ($shipment->status !== ShipmentStatus::APPROVED_LAB) {
                throw new \Exception('Shipment must be lab approved for final confirmation');
            }

            $oldStatus = $shipment->status;
            $shipment->update([
                'status' => ShipmentStatus::FINISHED,
                'final_confirmed_by' => $warehouseUser->id,
                'final_confirmed_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $warehouseUser, 'Final confirmation - quantities added to inventory');

            // Add tracking logs for each item in shipment
            foreach ($shipment->items as $shipmentItem) {
                if ($shipmentItem->quantity_received > 0) {
                    $this->trackingService->logShipmentReceipt(
                        $shipmentItem,
                        $shipment,
                        $shipmentItem->item,
                        $shipmentItem->quantity_received,
                        $warehouseUser
                    );
                }
            }

            return $shipment;
        });
    }

    /**
     * Get shipment with full tracking history
     */
    public function getShipmentWithTracking(int $shipmentId): Shipment
    {
        return Shipment::with([
            'items.item.unit',
            'warehouse',
            'adminApprovedBy',
            'purchaseUpdatedBy',
            'warehouseConfirmedBy',
            'sentToLabBy',
            'labApprovedBy',
            'finalConfirmedBy',
            'statusHistory.changedBy'
        ])->findOrFail($shipmentId);
    }

    /**
     * Get all shipments with filters for each role
     */
    public function getShipmentsByRole(string $role, array $filters = [])
    {
        $query = Shipment::with(['items.item', 'warehouse']);

        switch ($role) {
            case 'admin':
            case 'warehouse':
//                $query->whereIn('status', [
//                    ShipmentStatus::PENDING_ADMIN,
//                    ShipmentStatus::PENDING_PURCHASE,
//                ]);
                break;
            case 'sales':
                $query->where('status', ShipmentStatus::PENDING_PURCHASE);
                break;
            // case 'warehouse':
            //     $query->whereIn('status', [
            //         ShipmentStatus::READY_AT_WAREHOUSE,
            //         ShipmentStatus::PENDING_LAB,
            //         ShipmentStatus::APPROVED_LAB,
            //     ]);
            //     break;
            case 'tester':
                $query->whereIn('status', [ShipmentStatus::PENDING_LAB, ShipmentStatus::APPROVED_LAB, ShipmentStatus::REJECTED_LAB]);
                break;
            case 'finance':
                $query->where('status', ShipmentStatus::FINISHED);
                break;
        }

        // Apply filters
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['supplier'] ?? null) {
            $query->where('supplier', 'like', "%{$filters['supplier']}%");
        }
        if ($filters['date_from'] ?? null) {
            $query->whereDate('received_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate('received_at', '<=', $filters['date_to']);
        }

        // if(auth()->user()->hasRole('tester')){
        //     return $query->orderBy('created_at', 'desc')->paginate();
        // }
        return $query->orderBy('created_at', 'desc')->get();
    }
}
