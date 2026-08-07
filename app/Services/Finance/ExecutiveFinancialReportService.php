<?php

namespace App\Services\Finance;

use App\Models\Shipment;
use App\Models\ProductionOrder;
use App\Models\DistributionOrder;
use Carbon\Carbon;

class ExecutiveFinancialReportService
{
    /**
     * استخراج التقرير المالي الشامل والتنفيذي للجهة الإدارية والمالية
     */
    public function generateExecutiveReport(?string $startDate = null, ?string $endDate = null): array
    {
        // 1. ضبط النطاق الزمني للفترة الحالية
        $currentStart = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(29)->startOfDay();
        $currentEnd   = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // حساب عدد الأيام بالفترة الحالية لتحديد الفترة السابقة المماثلة تماماً
        $daysCount = $currentStart->diffInDays($currentEnd) + 1;

        // 2. تحديد النطاق الزمني للفترة السابقة (Month-over-Month)
        $priorEnd   = $currentStart->copy()->subDay()->endOfDay();
        $priorStart = $priorEnd->copy()->subDays($daysCount - 1)->startOfDay();

        // 3. استخراج بيانات الفترة الحالية (مجاميع + تفاصيل بالـ ID)
        $currentProcurement = $this->getProcurementSummary($currentStart, $currentEnd);
        $currentProduction  = $this->getProductionSummary($currentStart, $currentEnd);
        $currentSales       = $this->getSalesAndProfitabilitySummary($currentStart, $currentEnd);

        // 4. استخراج بيانات الفترة السابقة (للمقارنة فقط، لا نحتاج تفاصيلها المملة)
        $priorProcurement = $this->getProcurementSummary($priorStart, $priorEnd);
        $priorProduction  = $this->getProductionSummary($priorStart, $priorEnd);
        $priorSales       = $this->getSalesAndProfitabilitySummary($priorStart, $priorEnd);

        // 5. حساب المؤشرات الرئيسية للفترتين
        $currentKpis = $this->calculateKpiValues($currentSales, $currentProcurement, $currentProduction);
        $priorKpis   = $this->calculateKpiValues($priorSales, $priorProcurement, $priorProduction);

        // 6. مقارنة المؤشرات وبناء تحليل MoM
        $momComparison = $this->buildMomComparison($currentKpis, $priorKpis);

        return [
            'meta' => [
                'report_title' => 'التقرير المالي والتنفيذي الشامل لعمليات المعمل (مفصل بالحركات)',
                'generated_at' => now()->toDateTimeString(),
                'current_period' => [
                    'from'       => $currentStart->format('Y-m-d'),
                    'to'         => $currentEnd->format('Y-m-d'),
                    'days_count' => $daysCount,
                ],
                'prior_period' => [
                    'from'       => $priorStart->format('Y-m-d'),
                    'to'         => $priorEnd->format('Y-m-d'),
                    'days_count' => $daysCount,
                ],
            ],

            // المؤشرات الرئيسية
            'kpi_summary' => $currentKpis,
            'mom_comparison' => $momComparison,

            // التفاصيل المالية الدقيقة (تحتوي على الـ IDs ومحتوى كل أمر)
            'procurement_details'     => $currentProcurement,
            'production_details'      => $currentProduction,
            'sales_and_profitability' => $currentSales,
            
            // التنبيهات الذكية
            'financial_alerts'        => $this->generateFinancialAlerts($currentSales['product_breakdown'], $currentProduction['product_breakdown']),
        ];
    }

    private function calculateKpiValues(array $sales, array $procurement, array $production): array
    {
        $revenue = $sales['overall_revenue'];
        $cogs    = $sales['overall_cogs'];
        $profit  = $revenue - $cogs;
        $margin  = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

        return [
            'total_sales_revenue'      => round($revenue, 2),
            'total_cost_of_goods_sold' => round($cogs, 2),
            'gross_profit'             => round($profit, 2),
            'gross_profit_margin_pct'  => $margin,
            'total_raw_material_spent' => round($procurement['total_shipments_cost'], 2),
            'total_products_produced'  => round($production['total_produced_quantity'], 3),
            'total_products_sold'      => round($sales['total_quantity_sold'], 3),
        ];
    }

    private function buildMomComparison(array $current, array $prior): array
    {
        $metrics = [
            'sales_revenue'       => ['label' => 'إيرادات المبيعات', 'curr' => $current['total_sales_revenue'], 'prev' => $prior['total_sales_revenue']],
            'cogs'                => ['label' => 'تكلفة البضاعة المباعة', 'curr' => $current['total_cost_of_goods_sold'], 'prev' => $prior['total_cost_of_goods_sold']],
            'gross_profit'        => ['label' => 'مجمل الربح الصافي', 'curr' => $current['gross_profit'], 'prev' => $prior['gross_profit']],
            'gross_profit_margin' => ['label' => 'نسبة هامش الربح %', 'curr' => $current['gross_profit_margin_pct'], 'prev' => $prior['gross_profit_margin_pct']],
            'raw_material_spent'  => ['label' => 'إنفاق المشتريات', 'curr' => $current['total_raw_material_spent'], 'prev' => $prior['total_raw_material_spent']],
            'produced_quantity'   => ['label' => 'الكمية المنتجة', 'curr' => $current['total_products_produced'], 'prev' => $prior['total_products_produced']],
            'sold_quantity'       => ['label' => 'الكمية المباعة', 'curr' => $current['total_products_sold'], 'prev' => $prior['total_products_sold']],
        ];

        $comparison = [];
        foreach ($metrics as $key => $data) {
            $curr = $data['curr'];
            $prev = $data['prev'];
            $diff = $curr - $prev;
            $growthPct = ($prev > 0) ? round(($diff / $prev) * 100, 2) : (($prev == 0 && $curr > 0) ? 100.0 : 0.0);

            $comparison[$key] = [
                'metric_label'      => $data['label'],
                'current_period'    => $curr,
                'prior_period'      => $prev,
                'absolute_change'   => round($diff, 2),
                'growth_percentage' => $growthPct . '%',
                'trend'             => $diff > 0 ? 'INCREASED' : ($diff < 0 ? 'DECREASED' : 'STABLE'),
            ];
        }
        return $comparison;
    }

    /**
     * تفاصيل المشتريات (الشحنات + محتوى كل شحنة)
     */
    private function getProcurementSummary(Carbon $start, Carbon $end): array
    {
        $shipments = Shipment::with('items.item.unit')
            ->whereBetween('received_at', [$start, $end])
            ->get();

        $totalCost = $shipments->sum('total_price');
        $rawMaterialsPurchased = [];
        $detailedShipments = [];

        foreach ($shipments as $shipment) {
            $shipmentItemsDetails = [];
            $calculatedShipmentCost = 0;

            foreach ($shipment->items as $item) {
                $itemId = $item->item_id;
                $itemName = $item->item->name ?? 'غير محدد';
                $itemCost = $item->price ?? ($item->quantity_received * $item->unit_price);
                $calculatedShipmentCost += $itemCost;

                // 1. تجميع على مستوى المادة
                if (!isset($rawMaterialsPurchased[$itemId])) {
                    $rawMaterialsPurchased[$itemId] = [
                        'item_id' => $itemId,
                        'item_name' => $itemName,
                        'total_quantity_received' => 0,
                        'total_cost' => 0,
                    ];
                }
                $rawMaterialsPurchased[$itemId]['total_quantity_received'] += $item->quantity_received;
                $rawMaterialsPurchased[$itemId]['total_cost'] += $itemCost;

                // 2. تفاصيل المادة داخل هذه الشحنة (ID)
                $shipmentItemsDetails[] = [
                    'shipment_item_id' => $item->id,
                    'item_id'          => $itemId,
                    'item_name'        => $itemName,
                    'unit'             => $item->item->unit->name ?? '',
                    'quantity'         => $item->quantity_received,
                    'unit_price'       => round($item->unit_price, 2),
                    'total_item_cost'  => round($itemCost, 2),
                ];
            }

            // إضافة الفاتورة بسجلها الكامل
            $detailedShipments[] = [
                'shipment_id'     => $shipment->id,
                'supplier'        => $shipment->supplier,
                'supplier_number' => $shipment->supplier_number,
                'status'          => $shipment->status,
                'received_at'     => Carbon::parse($shipment->received_at)->format('Y-m-d'),
                'total_cost'      => round($calculatedShipmentCost, 2), // التأكد من دقة التكلفة الفعلي
                'items_count'     => count($shipmentItemsDetails),
                'items_details'   => $shipmentItemsDetails, // محتوى الفاتورة التفصيلي
            ];
        }

        return [
            'total_shipments_count'   => $shipments->count(),
            'total_shipments_cost'    => round($totalCost, 2),
            'raw_materials_breakdown' => array_values($rawMaterialsPurchased),
            'shipments_transaction_log' => $detailedShipments, // السجل التفصيلي المالي
        ];
    }

    /**
     * تفاصيل الإنتاج (أوامر الإنتاج + المواد المستهلكة في كل أمر)
     */
    private function getProductionSummary(Carbon $start, Carbon $end): array
    {
        $orders = ProductionOrder::with(['item.unit', 'materials.item', 'materials.shipmentItem'])
            ->where('type', 'production') // تصحيح: استخدام type بدلاً من Scope إذا لم يكن معرف
            ->whereIn('status', ['completed', 'finished'])
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalProducedQty = 0;
        $totalRawMaterialsCost = 0;
        $productBreakdown = [];
        $detailedProduction = [];

        foreach ($orders as $order) {
            $orderMaterialCost = 0;
            $consumedMaterialsDetails = [];

            foreach ($order->materials as $material) {
                // معرفة سعر الوحدة من الشحنة الأصلية (FEFO)
                $unitPrice = $material->shipmentItem->unit_price ?? 0;
                $cost = $material->consumed_quantity * $unitPrice;
                $orderMaterialCost += $cost;

                $consumedMaterialsDetails[] = [
                    'material_item_id' => $material->item_id,
                    'material_name'    => $material->item->name ?? '',
                    'source_shipment_item_id' => $material->shipment_item_id, // تتبع أصل المادة من أي شحنة!
                    'consumed_quantity'=> round($material->consumed_quantity, 3),
                    'unit_price'       => round($unitPrice, 2),
                    'total_cost'       => round($cost, 2),
                ];
            }

            $totalRawMaterialsCost += $orderMaterialCost;
            $producedQty = $order->produced_quantity;
            $totalProducedQty += $producedQty;
            $itemId = $order->item_id;

            // 1. تجميع على مستوى المنتج النهائي
            if (!isset($productBreakdown[$itemId])) {
                $productBreakdown[$itemId] = [
                    'item_id'                 => $itemId,
                    'product_name'            => $order->item->name ?? 'غير معروف',
                    'total_produced_quantity' => 0,
                    'total_material_cost'     => 0,
                ];
            }
            $productBreakdown[$itemId]['total_produced_quantity'] += $producedQty;
            $productBreakdown[$itemId]['total_material_cost'] += $orderMaterialCost;

            // 2. السجل التفصيلي لأمر الإنتاج
            $detailedProduction[] = [
                'production_order_id' => $order->id,
                'product_id'          => $itemId,
                'product_name'        => $order->item->name ?? 'غير معروف',
                'produced_quantity'   => $producedQty,
                'status'              => $order->status,
                'started_at'          => $order->started_at ? Carbon::parse($order->started_at)->format('Y-m-d H:i') : null,
                'total_order_cost'    => round($orderMaterialCost, 2),
                'unit_cost_for_this_order' => $producedQty > 0 ? round($orderMaterialCost / $producedQty, 2) : 0,
                'materials_consumed'  => $consumedMaterialsDetails, // المواد والتكلفة داخل هذا الأمر بالتحديد
            ];
        }

        foreach ($productBreakdown as &$prod) {
            $prod['average_cost_per_unit'] = $prod['total_produced_quantity'] > 0 ? round($prod['total_material_cost'] / $prod['total_produced_quantity'], 2) : 0;
            $prod['total_produced_quantity'] = round($prod['total_produced_quantity'], 3);
            $prod['total_material_cost'] = round($prod['total_material_cost'], 2);
        }

        return [
            'completed_orders_count'    => $orders->count(),
            'total_produced_quantity'   => round($totalProducedQty, 3),
            'total_raw_material_cost'   => round($totalRawMaterialsCost, 2),
            'product_breakdown'         => array_values($productBreakdown),
            'production_transaction_log'=> $detailedProduction, // السجل التفصيلي المالي
        ];
    }

    /**
     * تفاصيل المبيعات (طلبات التوزيع + المنتجات المباعة في كل طلب + تتبع التكلفة COGS)
     */
    private function getSalesAndProfitabilitySummary(Carbon $start, Carbon $end): array
    {
        $orders = DistributionOrder::with([
            'items.item.unit',
            'items.allocations.shipmentItem'
        ])
        ->whereNotNull('sold_at')
        ->whereBetween('sold_at', [$start, $end])
        ->get();

        $overallRevenue = 0;
        $overallCogs = 0;
        $totalQtySold = 0;
        $productMarginBreakdown = [];
        $detailedSalesOrders = [];

        foreach ($orders as $order) {
            $orderRevenue = 0;
            $orderCogs = 0;
            $orderItemsDetails = [];

            foreach ($order->items as $orderItem) {
                $itemRevenue = $orderItem->total_price;
                $itemCogs = 0;
                $cogsBreakdown = []; // تفصيل من أين تم سحب البضاعة لحساب التكلفة بدقة للمالية

                foreach ($orderItem->allocations as $allocation) {
                    $batchUnitCost = $allocation->shipmentItem->unit_price ?? 0;
                    $allocationCost = $allocation->allocated_quantity * $batchUnitCost;
                    $itemCogs += $allocationCost;

                    $cogsBreakdown[] = [
                        'source_shipment_item_id' => $allocation->shipment_item_id, // تتبع من أي مستودع/شحنة
                        'allocated_quantity'      => round($allocation->allocated_quantity, 3),
                        'batch_unit_cost'         => round($batchUnitCost, 2),
                        'total_allocation_cost'   => round($allocationCost, 2),
                    ];
                }

                $orderRevenue += $itemRevenue;
                $orderCogs += $itemCogs;
                $itemNetProfit = $itemRevenue - $itemCogs;

                $itemId = $orderItem->item_id;

                // 1. تجميع الأرباح على مستوى المادة
                if (!isset($productMarginBreakdown[$itemId])) {
                    $productMarginBreakdown[$itemId] = [
                        'item_id' => $itemId,
                        'product_name' => $orderItem->item->name ?? 'غير معروف',
                        'total_quantity_sold' => 0,
                        'total_revenue' => 0,
                        'total_cogs' => 0,
                    ];
                }
                $productMarginBreakdown[$itemId]['total_quantity_sold'] += $orderItem->quantity;
                $productMarginBreakdown[$itemId]['total_revenue'] += $itemRevenue;
                $productMarginBreakdown[$itemId]['total_cogs'] += $itemCogs;

                // 2. السطر التفصيلي داخل أمر البيع
                $orderItemsDetails[] = [
                    'distribution_order_item_id' => $orderItem->id,
                    'item_id'                    => $itemId,
                    'product_name'               => $orderItem->item->name ?? '',
                    'quantity_sold'              => round($orderItem->quantity, 3),
                    'selling_price_per_ton'      => round($orderItem->price_per_ton, 2),
                    'total_revenue'              => round($itemRevenue, 2),
                    'total_cogs'                 => round($itemCogs, 2),
                    'net_profit'                 => round($itemNetProfit, 2),
                    'profit_margin'              => $itemRevenue > 0 ? round(($itemNetProfit / $itemRevenue) * 100, 2) . '%' : '0%',
                    'cogs_trace_allocations'     => $cogsBreakdown, // تتبع دقيق من أين جاءت التكلفة (FEFO)
                ];
            }

            $orderProfit = $orderRevenue - $orderCogs;
            $overallRevenue += $orderRevenue;
            $overallCogs += $orderCogs;

            // 3. السجل التفصيلي لأمر البيع
            $detailedSalesOrders[] = [
                'distribution_order_id' => $order->id,
                'customer_name'         => $order->customer_name ?? 'عميل نقدي',
                'sold_at'               => Carbon::parse($order->sold_at)->format('Y-m-d H:i'),
                'total_order_revenue'   => round($orderRevenue, 2),
                'total_order_cogs'      => round($orderCogs, 2),
                'order_net_profit'      => round($orderProfit, 2),
                'order_profit_margin'   => $orderRevenue > 0 ? round(($orderProfit / $orderRevenue) * 100, 2) . '%' : '0%',
                'items_count'           => count($orderItemsDetails),
                'items_details'         => $orderItemsDetails, // محتوى أمر البيع بالكامل وكل منتج كم ربح
            ];
        }

        foreach ($productMarginBreakdown as &$item) {
            $itemProfit = $item['total_revenue'] - $item['total_cogs'];
            $item['net_profit'] = round($itemProfit, 2);
            $item['profit_margin_pct'] = $item['total_revenue'] > 0 ? round(($itemProfit / $item['total_revenue']) * 100, 2) : 0;
            $totalQtySold += $item['total_quantity_sold'];
            
            $item['total_quantity_sold'] = round($item['total_quantity_sold'], 3);
            $item['total_revenue'] = round($item['total_revenue'], 2);
            $item['total_cogs'] = round($item['total_cogs'], 2);
        }

        return [
            'total_sales_orders_count' => $orders->count(),
            'total_quantity_sold'      => round($totalQtySold, 3),
            'overall_revenue'          => round($overallRevenue, 2),
            'overall_cogs'             => round($overallCogs, 2),
            'overall_net_profit'       => round($overallRevenue - $overallCogs, 2),
            'product_breakdown'        => array_values($productMarginBreakdown),
            'sales_transaction_log'    => $detailedSalesOrders, // سجل البيع وتتبع التكلفة لكل فاتورة
        ];
    }

    /**
     * تنبيهات الذكاء المالي
     */
    private function generateFinancialAlerts(array $salesProducts, array $productionProducts): array
    {
        $alerts = [];
        foreach ($salesProducts as $product) {
            if ($product['profit_margin_pct'] < 5 && $product['profit_margin_pct'] >= 0) {
                $alerts[] = [
                    'type' => 'LOW_MARGIN',
                    'severity' => 'HIGH',
                    'message' => "المنتج '{$product['product_name']}' يحقق هامش ربح منخفض بنسبة ({$product['profit_margin_pct']}%).",
                ];
            }
            if ($product['net_profit'] < 0) {
                $alerts[] = [
                    'type' => 'NEGATIVE_PROFIT',
                    'severity' => 'CRITICAL',
                    'message' => "المنتج '{$product['product_name']}' يباع بخسارة مالية قدرها ({$product['net_profit']}).",
                ];
            }
        }
        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'HEALTHY',
                'severity' => 'INFO',
                'message' => 'جميع المؤشرات المالية ضمن النطاق المستهدف.',
            ];
        }
        return $alerts;
    }
}