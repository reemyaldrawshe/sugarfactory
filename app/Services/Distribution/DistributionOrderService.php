<?php

namespace App\Services\Distribution;

use App\Models\DistributionOrder;
use App\Models\Item;
use App\Enums\ProductionStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionOrderService
{

/**
     * جلب قائمة طلبات التوزيع مع كافة تفاصيل المواد والدفعات المخصصة لها
     */
    public function getAllWithFullDetails()
    {
        return DistributionOrder::query()
            ->with([
                'user:id,name', // معلومات الموظف الذي أنشأ الطلب
                'items.item:id,name,sku', // المواد المطلوبة داخل الطلب مع اسمها وكودها
                'batchAllocations' => function ($query) {
                    // جلب تفاصيل سحب الدفعات مرتبة حسب الصلاحية لسهولة القراءة
                    $query->with(['shipmentItem:id,batch_number,expiry_date']);
                }
            ])
            ->latest() // ترتيب من الأحدث إلى الأقدم
            ->get()
            ->map(function ($order) {
                // 💡 هندسة البيانات لتسهيل قراءتها على الـ Front-End
                return [
                    'id' => $order->id,
                    'customer_name' => $order->customer_name,
                    'status' => $order->status,
                    'notes' => $order->notes,
                    'created_by' => $order->user?->name,
                    'created_at' => $order->created_at->toDateTimeString(),
                    'approved_at' => $order->approved_at?->toDateTimeString(),
                    
                    // المواد المطلوبة
                    'requested_items' => $order->items->map(function ($item) use ($order) {
                        
                        // فلترة الدفعات المخصصة لهذه المادة بالتحديد داخل هذا الطلب
                        $allocations = $order->batchAllocations
                            ->where('distribution_order_item_id', $item->id);

                        return [
                            'item_id' => $item->item_id,
                            'item_name' => $item->item?->name,
                            'sku' => $item->item?->sku,
                            'total_requested_quantity' => (float) $item->quantity,
                            'price_per_ton' => (float) $item->price_per_ton,
                            'total_price' => (float) $item->total_price,
                            
                            // 🚚 خطة السحب من الدفعات لأمين المستودع (خوارزمية FEFO المطبقة)
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
        // استخدام Transaction لضمان حفظ الطلب ومواده معاً أو التراجع عن كل شيء في حال الخطأ
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

                // التحقق: لا يمكن بيع المواد الخام مباشرة (اختياري حسب البزنس لوجيك عندك)
                if ($item->is_raw_material == 1) {
                    throw ValidationException::withMessages([
                        'items' => "عذراً، المادة ({$item->name}) هي مادة خام ولا يمكن إضافتها لطلب مبيعات."
                    ]);
                }

                // 💡 أخذ لقطة (Snapshot) للسعر الحالي للمادة من قسم المالية
                // افترضت أن حقل السعر في جدول المواد اسمه price، يمكنك تغييره حسب اسم الحقل لديك
                // $currentPricePerTon = $item->selling_price ?? 0; 
                // $quantity = (float) $requestedItem['quantity'];
                
                // // حساب السعر الإجمالي لهذا السطر
                // $totalPrice = $quantity * $currentPricePerTon;
// 2. 💡 التحقق الوقائي الجديد: التأكد من أن المادة مسعّرة في النظام
if (is_null($item->selling_price) || $item->selling_price <= 0) {
    throw ValidationException::withMessages([
        'items' => "عذراً، المادة ({$item->name}) لم يتم تحديد سعر بيع صالح لها من قسم المالية بعد."
    ]);
}

$currentPricePerTon = (float) $item->selling_price;
$quantity = (float) $requestedItem['quantity'];
$totalPrice = $quantity * $currentPricePerTon;
                // 3. إنشاء سطر تفاصيل المادة داخل الطلب
                $order->items()->create([
                    'item_id'       => $item->id,
                    'quantity'      => $quantity,
                    'price_per_ton' => $currentPricePerTon, // تم التثبيت التاريخي للسعر
                    'total_price'   => $totalPrice,
                ]);
            }

            // إرجاع الطلب مع مواده المحملة لعرضه في الاستجابة (API Response)
            return $order->load('items.item');
        });
    }
}