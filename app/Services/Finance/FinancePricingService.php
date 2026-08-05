<?php

namespace App\Services\Finance;

use App\Models\Item;
use Exception;

class FinancePricingService
{
    /**
     * جلب تفاصيل التسعير والتكلفة لمادة معينة
     */
    public function getItemPricingAnalysis(int $itemId)
    {
        // 1. جلب المادة مع الدفعات (ShipmentItems) التي تحتوي على كمية فعلية
        $item = Item::with(['unit', 'shipmentItems' => function ($query) {
            // نجلب فقط الدفعات التي كميتها المستلمة أكبر من 0 (لم تنفد بعد)
            $query->where('quantity_received', '>', 0);
        }])->findOrFail($itemId);

        $activeBatches = [];
        $totalAvailableQuantity = 0;
        $totalCostOfAvailableQuantities = 0;

        // 2. المرور على الدفعات المتوفرة لحساب التكلفة الإجمالية والكميات
        foreach ($item->shipmentItems as $batch) {
            // الكمية المتاحة فعلياً للبيع = الكمية الموجودة - الكمية المحجوزة لطلبات أخرى
            $availableQty = $batch->quantity_received - $batch->quantity_reserved;
            
            // إذا كانت الدفعة محجوزة بالكامل، نتجاوزها
            if ($availableQty <= 0) continue;

            // تكلفة الوحدة في هذه الدفعة (أنت قمت ببرمجتها مسبقاً في Accessor)
            $unitCost = $batch->unit_price; 
            
            // التكلفة الإجمالية لهذه الدفعة بناءً على الكمية المتبقية منها
            $batchTotalCost = $availableQty * $unitCost;

            // إضافة القيم للإجماليات
            $totalAvailableQuantity += $availableQty;
            $totalCostOfAvailableQuantities += $batchTotalCost;

            // حفظ تفاصيل الدفعة لتعرض للمالية
            $activeBatches[] = [
                'batch_id'            => $batch->id,
                'production_order_id' => $batch->production_order_id,
                'expiry_status'       => $batch->expiry_status,
                'available_quantity'  => $availableQty,
                'unit_cost'           => round($unitCost, 2),
                'batch_total_cost'    => round($batchTotalCost, 2),
            ];
        }

        // 3. حساب المتوسط المرجح للتكلفة (Weighted Average Cost)
        $averageUnitCost = 0;
        if ($totalAvailableQuantity > 0) {
            $averageUnitCost = $totalCostOfAvailableQuantities / $totalAvailableQuantity;
        }

        // 4. حساب الأرباح المتوقعة بناءً على سعر البيع الحالي للمنتج
        $sellingPrice = $item->selling_price ?? 0;
        $expectedProfitPerUnit = $sellingPrice - $averageUnitCost;
        
        // حساب هامش الربح كنسبة مئوية (%)
        $profitMarginPercentage = $sellingPrice > 0 
            ? (($expectedProfitPerUnit / $sellingPrice) * 100) 
            : 0;

        // إجمالي الربح المتوقع في حال بيع كامل الكمية المتوفرة
        $totalExpectedProfit = $expectedProfitPerUnit * $totalAvailableQuantity;

        // 5. إرجاع التقرير الشامل
        return [
            'item' => [
                'id'            => $item->id,
                'name'          => $item->name,
                'unit'          => $item->unit_name,
                'selling_price' => round($sellingPrice, 2),
            ],
            'inventory_summary' => [
                'total_available_quantity' => $totalAvailableQuantity,
                'active_batches_count'     => count($activeBatches),
            ],
            'cost_and_profit_analysis' => [
                'average_unit_cost'          => round($averageUnitCost, 2),
                'expected_profit_per_unit'   => round($expectedProfitPerUnit, 2),
                'profit_margin_percentage'   => round($profitMarginPercentage, 2) . '%',
                'total_expected_profit'      => round($totalExpectedProfit, 2),
            ],
            'batches_details' => $activeBatches
        ];
    }

    public function updateSellingPrice(int $itemId, float $newPrice)
    {
        $item = Item::findOrFail($itemId);
        
        // تحديث السعر
        $item->selling_price = $newPrice;
        $item->save();

        // إرجاع التقرير الجديد ليعكس الأرباح والنسب الجديدة بناءً على السعر المحدث
        return $this->getItemPricingAnalysis($itemId);
    }
}