<?php

namespace App\Services\Finance;

use App\Models\ProductionOrder;
use Carbon\Carbon;

class ProductionCostService
{
    /**
     * استخراج تقرير تكاليف الإنتاج لفترة محددة ولمادة معينة
     */
    public function getProductionCostReport(?string $startDate = null, ?string $endDate = null, ?int $itemId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subMonth()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // بناء الاستعلام الأساسي
        $query = ProductionOrder::with([
            'item.unit', 
            'materials.item.unit', 
            'materials.shipmentItem'
        ])
        ->production()
        ->whereIn('status', ['completed', 'finished'])
        ->whereBetween('created_at', [$start, $end]);

        // 💡 التعديل الأول: فلترة حسب المادة المنتجة إذا اختارها المستخدم
        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        $orders = $query->get();

        $report = [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'summary' => [
                'total_orders_count' => 0,
                'total_produced_quantities' => 0,
                'total_materials_cost' => 0,
                'average_cost_per_unit' => 0, // 💡 التعديل الثاني: حقل المتوسط العام
            ],
            'orders' => []
        ];

        foreach ($orders as $order) {
            $orderTotalCost = 0;
            $materialsBreakdown = [];

            foreach ($order->materials as $material) {
                $unitPrice = $material->shipmentItem ? $material->shipmentItem->unit_price : 0;
                $materialTotalCost = $material->consumed_quantity * $unitPrice;
                $orderTotalCost += $materialTotalCost;

                $materialsBreakdown[] = [
                    'material_name' => $material->item->name ?? 'غير معروف',
                    'unit' => $material->item->unit->name ?? '',
                    'batch_id' => $material->shipment_item_id,
                    'consumed_quantity' => (float) $material->consumed_quantity,
                    'unit_cost' => (float) $unitPrice,
                    'total_cost' => (float) $materialTotalCost,
                ];
            }

            $producedQty = $order->produced_quantity > 0 ? $order->produced_quantity : 1;
            $costPerUnit = $orderTotalCost / $producedQty;

            $report['orders'][] = [
                'order_id' => $order->id,
                'product_name' => $order->item->name ?? 'غير معروف',
                'product_unit' => $order->item->unit->name ?? '',
                'produced_quantity' => (float) $order->produced_quantity,
                'total_production_cost' => (float) $orderTotalCost,
                'cost_per_unit' => (float) $costPerUnit,
                'date' => $order->created_at->format('Y-m-d'),
                'materials_details' => $materialsBreakdown,
            ];

            $report['summary']['total_orders_count']++;
            $report['summary']['total_produced_quantities'] += $order->produced_quantity;
            $report['summary']['total_materials_cost'] += $orderTotalCost;
        }

        // 💡 التعديل الثالث: حساب متوسط التكلفة العام للفترة
        $totalQty = $report['summary']['total_produced_quantities'];
        if ($totalQty > 0) {
            $report['summary']['average_cost_per_unit'] = (float) ($report['summary']['total_materials_cost'] / $totalQty);
        }

        return $report;
    }
}