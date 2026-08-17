<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Http\Requests\Tester\Shipment\LabApproveRequest;
use App\Models\Item;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\NotificationService;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ShipmentService
{
    protected $trackingService;
    protected $notificationService;

    public function __construct(
        ItemTrackingService $trackingService,
        NotificationService $notificationService
    ) {
        $this->trackingService = $trackingService;
        $this->notificationService = $notificationService;
    }

    /**
     * 1. Warehouse creates purchase request
     */
    public function createPurchaseRequest(array $data, $warehouseUser): Shipment
    {
        return DB::transaction(function () use ($data, $warehouseUser) {
            $shipment = Shipment::create([
                'received_at'  => now(),
                'status'       => ShipmentStatus::PENDING_ADMIN,
                'warehouse_id' => $warehouseUser->id,
                'notes'        => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                ShipmentItem::create([
                    'shipment_id'       => $shipment->id,
                    'item_id'           => $item['item_id'],
                    'quantity_required' => $item['quantity_required'],
                    'quantity_received' => 0,
                ]);
            }

            $shipment->recordStatusChange(
                ShipmentStatus::PENDING_ADMIN,
                $warehouseUser,
                'Purchase request created'
            );

            // 🔔 إرسال إشعار للمدراء بوجود طلب شراء جديد
            $admins = $this->getUsersByRoles(['admin']);
            $userName = $warehouseUser->name ?? ($warehouseUser->first_name ?? 'أمين المستودع');

            $this->notifyUsers(
                $admins,
                'طلب شراء جديد',
                "تم إنشاء طلب شراء جديد رقم #{$shipment->id} بواسطة {$userName}",
                'purchase_request_created',
                ['shipment_id' => $shipment->id]
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
                'status'            => ShipmentStatus::PENDING_PURCHASE,
                'admin_approved_by' => $adminUser->id,
                'admin_approved_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $adminUser, 'Admin approved purchase request');

            // 🔔 إرسال إشعار للمشتريات وأمين المستودع
            $targetUsers = $this->getUsersByRoles(['sales', 'warehouse']);

            $this->notifyUsers(
                $targetUsers,
                'موافقة على طلب الشراء',
                "وافق المدير على طلب الشراء رقم #{$shipment->id} وهو جاهز للشراء الآن",
                'admin_approved',
                ['shipment_id' => $shipment->id]
            );

            return $shipment;
        });
    }

    /**
     * Pay shipment (Finance)
     */
    public function payShipment(int $shipmentId, User $financeUser): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $financeUser) {
            $shipment = Shipment::findOrFail($shipmentId);

            if (!$shipment->canTransitionTo(ShipmentStatus::PAID)) {
                throw new \Exception('لا يمكن دفع هذه الشحنة لأنها ليست منتهية (Finished).');
            }

            $oldStatus = $shipment->status;

            $shipment->update([
                'status'  => ShipmentStatus::PAID,
                'paid_by' => $financeUser->id,
                'paid_at' => now(),
            ]);

            $shipment->recordStatusChange(
                $oldStatus, 
                $financeUser, 
                'تم دفع قيمة الشحنة من قبل قسم المالية'
            );

            // 🔔 إشعار للمدير والمستودع بدفع الشحنة
            $targetUsers = $this->getUsersByRoles(['admin', 'warehouse']);
            $this->notifyUsers(
                $targetUsers,
                'تم دفع قيمة الشحنة',
                "تم دفع قيمة الشحنة رقم #{$shipment->id} من قبل قسم المالية",
                'shipment_paid',
                ['shipment_id' => $shipment->id]
            );

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

            // 💡 معالجة رفع صور الفواتير المتعددة باستخدام Spatie Media Library
            if (isset($data['invoice_images']) && is_array($data['invoice_images'])) {
                foreach ($data['invoice_images'] as $image) {
                    $shipment->addMedia($image)->toMediaCollection('invoice_images');
                }
            }
            
            $totalPrice = 0;

            foreach ($data['items'] as $itemData) {
                $itemId = $itemData['item_id'] ?? $itemData['id'];

                $shipmentItem = ShipmentItem::where('shipment_id', $shipment->id)
                    ->where(function ($query) use ($itemId) {
                        $query->where('item_id', $itemId)
                              ->orWhere('id', $itemId);
                    })
                    ->firstOrFail();

                if (isset($itemData['price']) && $itemData['price'] != $shipmentItem->price) {
                    $shipmentItem->updatePrice($itemData['price'], $salesUser);
                }

                if (isset($itemData['quantity_received']) && $itemData['quantity_received'] != $shipmentItem->quantity_received) {
                    $shipmentItem->updateQuantity($itemData['quantity_received'], $salesUser);
                }

                $currentPrice = $itemData['price'] ?? $shipmentItem->price;
                $currentQty   = $itemData['quantity_received'] ?? $shipmentItem->quantity_received;
                $totalPrice  += $currentPrice;
                
                $unitPrice = ($currentQty > 0) ? ($currentPrice / $currentQty) : 0;

                $shipmentItem->update([
                    'unit_price'  => $unitPrice ?? $shipmentItem->unit_price, 
                    'expiry_date' => $itemData['expiry_date'] ?? $shipmentItem->expiry_date,
                    'note'        => $itemData['note'] ?? $shipmentItem->note,
                ]);
            }

            $oldStatus = $shipment->status;
            $shipment->update([
                'supplier'            => $data['supplier'] ?? $shipment->supplier,
                'supplier_number'     => $data['supplier_number'] ?? $shipment->supplier_number,
                'status'              => ShipmentStatus::READY_AT_WAREHOUSE,
                'total_price'         => $totalPrice,
                'purchase_updated_by' => $salesUser->id,
                'purchase_updated_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $salesUser, 'Purchase request updated with prices, quantities and invoices');

            // 🔔 إرسال إشعار لأمين المستودع للاستلام
            $warehouseUsers = $this->getUsersByRoles(['warehouse']);
            $this->notifyUsers(
                $warehouseUsers,
                'طلب جاهز للاستلام',
                "تم تحديث بيانات طلب الشراء رقم #{$shipment->id} وهو جاهز للاستلام في المستودع",
                'ready_for_receipt',
                ['shipment_id' => $shipment->id]
            );

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
                'status'                 => ShipmentStatus::PENDING_LAB,
                'warehouse_confirmed_by' => $warehouseUser->id,
                'warehouse_confirmed_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $warehouseUser, 'Warehouse confirmed receipt before lab testing');

            // 🔔 إرسال إشعار للمخبر بوجود شحنة جديدة تتطلب الفحص
            $labUsers = $this->getUsersByRoles(['tester']); 
            $this->notifyUsers(
                $labUsers,
                'شحنة جديدة للفحص المخبري',
                "تم تأكيد استلام الشحنة رقم #{$shipment->id} وهي جاهزة للفحص في المختبر الآن",
                'pending_lab',
                ['shipment_id' => $shipment->id]
            );

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

            // 🔔 إرسال إشعار للمخبر بأن هناك شحنة بانتظار الفحص
            $labUsers = $this->getUsersByRoles(['tester']);
            $this->notifyUsers(
                $labUsers,
                'طلب فحص مخبري',
                "تم تحويل الشحنة رقم #{$shipment->id} إلى المخبر لطلب إجراء الفحص",
                'sent_to_lab',
                ['shipment_id' => $shipment->id]
            );

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

            if (isset($data['lab_test_file'])) {
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
                'status'          => ShipmentStatus::APPROVED_LAB,
                'lab_approved_by' => $testerUser->id,
                'lab_approved_at' => now(),
            ]);

            $requestItems = $request->input('items', []);

            foreach ($requestItems as $requestItem) {
                $shipmentItem = $shipment->items->where('id', $requestItem['shipment_item_id'])->first();

                if ($shipmentItem) {
                    $shipmentItem->update([
                        'expiry_date' => $requestItem['expiry_date']
                    ]);
                }
            }

            $shipment->recordStatusChange($oldStatus, $testerUser, 'Lab approved shipment');

            // 🔔 إرسال إشعار للمدير وأمين المستودع بقبول الشحنة
            $targetUsers = $this->getUsersByRoles(['admin', 'warehouse']);
            $this->notifyUsers(
                $targetUsers,
                'قبول الفحص المخبري',
                "اجتازت الشحنة رقم #{$shipment->id} الفحص المخبري بنجاح وتمت الموافقة عليها",
                'lab_approved',
                ['shipment_id' => $shipment->id]
            );

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
                'status'               => ShipmentStatus::REJECTED_LAB,
                'lab_rejection_reason' => $reason,
            ]);

            $shipment->recordStatusChange($oldStatus, $testerUser, "Lab rejected shipment: $reason");

            // 🔔 إرسال إشعار للمدير وأمين المستودع برفض الشحنة
            $targetUsers = $this->getUsersByRoles(['admin', 'warehouse']);
            $this->notifyUsers(
                $targetUsers,
                'رفض الفحص المخبري',
                "تم رفض الشحنة رقم #{$shipment->id} من قبل المخبر. السبب: {$reason}",
                'lab_rejected',
                ['shipment_id' => $shipment->id]
            );

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
                'status'             => ShipmentStatus::FINISHED,
                'final_confirmed_by' => $warehouseUser->id,
                'final_confirmed_at' => now(),
            ]);

            $shipment->recordStatusChange($oldStatus, $warehouseUser, 'Final confirmation - quantities added to inventory');

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

            // 🔔 إرسال إشعار للمالية بأن الفاتورة جاهزة للدفع
            $financeUsers = $this->getUsersByRoles(['finance']);
            $this->notifyUsers(
                $financeUsers,
                'فاتورة شحنة جديدة',
                "تم التأكيد النهائي للشحنة رقم #{$shipment->id} وإدخالها للمستودع، الفاتورة جاهزة للمعالجة المالية",
                'final_confirmed',
                ['shipment_id' => $shipment->id]
            );

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
            'paidBy',
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
                break;
            case 'sales':
            case 'purchasing':
                $query->where('status', ShipmentStatus::PENDING_PURCHASE);
                break;
            case 'tester':
            case 'lab':
                $query->whereIn('status', [ShipmentStatus::PENDING_LAB, ShipmentStatus::APPROVED_LAB, ShipmentStatus::REJECTED_LAB]);
                break;
            case 'finance':
                $query->whereIn('status', [ShipmentStatus::FINISHED, ShipmentStatus::PAID]);
                break;
        }

        if (!empty($filters['status'])) {
            $status = $filters['status'];
            if (is_string($status) && enum_exists(ShipmentStatus::class)) {
                $status = ShipmentStatus::tryFrom($status) ?? $status;
            }
            $query->where('status', $status);
        }

        if (!empty($filters['supplier'])) {
            $query->where('supplier', 'like', "%{$filters['supplier']}%");
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('received_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('received_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * 🔍 جلب المستخدمين حسب الدور (يدعم Spatie Role أو العمود العادي role)
     */
    private function getUsersByRoles(array $roles)
    {
        if (method_exists(User::class, 'scopeRole')) {
            return User::role($roles)->get();
        }

        return User::whereIn('role', $roles)->get();
    }

    /**
     * 📩 حفظ الإشعار في قاعدة البيانات وإرساله للمستخدمين عبر NotificationService
     */
    private function notifyUsers($users, string $title, string $message, string $type = 'shipment', array $extraData = [])
    {
        if (!$users || (is_countable($users) && count($users) === 0)) {
            return;
        }

        if ($users instanceof User) {
            $users = collect([$users]);
        }

        foreach ($users as $user) {
            try {
                // استدعاء NotificationService لتقوم بالحفظ في الداتا بيز والإرسال إلى Firebase معاً
                $this->notificationService->send($user, $title, $message, $type, $extraData);
            } catch (\Throwable $e) {
                Log::error("Failed to send notification to user ID {$user->id}: " . $e->getMessage());
            }
        }
    }
}