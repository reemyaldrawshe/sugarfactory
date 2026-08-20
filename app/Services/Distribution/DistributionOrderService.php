<?php

namespace App\Services\Distribution;

use App\Models\DistributionOrder;
use App\Models\Item;
use App\Models\User;
use App\Enums\ProductionStatusEnum;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DistributionOrderService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * جلب قائمة طلبات التوزيع مع كافة تفاصيل المواد والدفعات المخصصة لها
     */
    public function getAllWithFullDetails()
    {
        return DistributionOrder::query()
            ->with([
                'user:id,name',
                'items.item:id,name,sku',
                'batchAllocations' => function ($query) {
                    $query->with(['shipmentItem:id,batch_number,expiry_date']);
                }
            ])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'customer_name' => $order->customer_name,
                    'status' => $order->status,
                    'notes' => $order->notes,
                    'created_by' => $order->user?->name,
                    'created_at' => $order->created_at->toDateTimeString(),
                    'approved_at' => $order->approved_at?->toDateTimeString(),
                    
                    'requested_items' => $order->items->map(function ($item) use ($order) {
                        $allocations = $order->batchAllocations
                            ->where('distribution_order_item_id', $item->id);

                        return [
                            'item_id' => $item->item_id,
                            'item_name' => $item->item?->name,
                            'sku' => $item->item?->sku,
                            'total_requested_quantity' => (float) $item->quantity,
                            'price_per_ton' => (float) $item->price_per_ton,
                            'total_price' => (float) $item->total_price,
                            
                            'warehouse_withdrawal_plan' => $allocations->map(function ($alloc) {
                                return [
                                    'batch_number' => $alloc->shipmentItem?->id ?? 'N/A',
                                    'expiry_date' => $alloc->shipmentItem?->expiry_date,
                                    'quantity_to_withdraw' => (float) $alloc->allocated_quantity
                                ];
                            })->values()
                        ];
                    })
                ];
            });
    }

    /**
     * إنشاء طلب مبيعات/توزيع جديد مع مواده
     */
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            
            // 1. إنشاء الطلب الرئيسي
            $order = DistributionOrder::create([
                'user_id'       => auth()->id(),
                'customer_name' => $data['customer_name'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'status'        => ProductionStatusEnum::DIST_PENDING->value,
            ]);

            // 2. المرور على مصفوفة المواد لربطها بالطلب وتثبيت أسعارها
            foreach ($data['items'] as $requestedItem) {
                $item = Item::findOrFail($requestedItem['item_id']);

                if ($item->is_raw_material == 1) {
                    throw ValidationException::withMessages([
                        'items' => "عذراً، المادة ({$item->name}) هي مادة خام ولا يمكن إضافتها لطلب مبيعات."
                    ]);
                }

                if (is_null($item->selling_price) || $item->selling_price <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "عذراً، المادة ({$item->name}) لم يتم تحديد سعر بيع صالح لها من قسم المالية بعد."
                    ]);
                }

                $currentPricePerTon = (float) $item->selling_price;
                $quantity = (float) $requestedItem['quantity'];
                $totalPrice = $quantity * $currentPricePerTon;

                $order->items()->create([
                    'item_id'       => $item->id,
                    'quantity'      => $quantity,
                    'price_per_ton' => $currentPricePerTon,
                    'total_price'   => $totalPrice,
                ]);
            }

            // 🔔 3. إرسال إشعار للمدير بوجود طلب بيع جديد بحاجة لموافقة
            $adminUsers = $this->getUsersByRoles(['admin']);
            $this->notifyUsers(
                $adminUsers,
                'طلب بيع جديد بانتظار الموافقة',
                "تم إنشاء طلب مبيعات جديد رقم #{$order->id} للعميل ({$order->customer_name})",
                'distributionOrderService',
            ['distribution_order_id' => $order->id,
             'action'=>'create'
            ]
            );

            return $order->load('items.item');
        });
    }

    /**
     * 🔍 جلب المستخدمين حسب الأدوار
     */
    private function getUsersByRoles(array $roles)
    {
        if (method_exists(User::class, 'scopeRole')) {
            return User::role($roles)->get();
        }

        return User::whereIn('role', $roles)->get();
    }

    /**
     * 📩 إرسال الإشعار وحفظه عبر NotificationService
     */
    private function notifyUsers($users, string $title, string $message, string $type = 'distribution', array $extraData = [])
    {
        if (!$users || (is_countable($users) && count($users) === 0)) {
            return;
        }

        if ($users instanceof User) {
            $users = collect([$users]);
        }

        foreach ($users as $user) {
            try {
                $this->notificationService->send($user, $title, $message, $type, $extraData);
            } catch (\Throwable $e) {
                Log::error("Failed to send notification to user ID {$user->id}: " . $e->getMessage());
            }
        }
    }
}