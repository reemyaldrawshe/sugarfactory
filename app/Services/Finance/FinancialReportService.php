<?php

namespace App\Services\Finance;

use App\Models\DistributionOrder;
use Carbon\Carbon;

class FinancialReportService
{

/**
     * توليد تقرير أرباح المبيعات خلال فترة محددة
     */
    public function getSalesAndProfitReport(?string $startDate = null, ?string $endDate = null): array
    {
        // 1. تحديد التواريخ (الافتراضي: آخر 30 يوماً وحتى اليوم)
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        // 2. جلب الطلبات "المباعة" ضمن النطاق الزمني مع علاقاتها المعقدة
        $orders = DistributionOrder::with([
            'items.item.unit', // جلب المادة ووحدتها
            'items.allocations.shipmentItem' // جلب توزيعات الدفعات (لمعرفة التكلفة الدقيقة لكل دفعة)
        ])
        ->whereNotNull('sold_at') // نجلب فقط الطلبات التي تمت عملية بيعها فعلياً
        ->whereBetween('sold_at', [$start, $end])
        ->orderBy('sold_at', 'desc')
        ->get();

        // 3. تهيئة متحولات الإجماليات للتقرير العام
        $overallTotalRevenue = 0;
        $overallTotalCost = 0;
        $overallTotalProfit = 0;
        
        $ordersReport = [];

        // 4. المرور على الطلبات وحساب تفاصيل كل طلب
        foreach ($orders as $order) {
            $orderRevenue = 0;
            $orderCost = 0;
            $itemsReport = [];

            foreach ($order->items as $orderItem) {
                $itemCost = 0;
                $batchesUsed = [];

                // أ. حساب تكلفة هذا العنصر بناءً على الدفعات التي تم السحب منها
                foreach ($orderItem->allocations as $allocation) {
                    $shipmentItem = $allocation->shipmentItem;
                    
                    // التكلفة للكمية المسحوبة من هذه الدفعة = الكمية × سعر وحدة الدفعة
                    $batchUnitCost = $shipmentItem->unit_price ?? 0;
                    $allocatedCost = $allocation->allocated_quantity * $batchUnitCost;
                    
                    $itemCost += $allocatedCost;

                    $batchesUsed[] = [
                        'batch_id'           => $shipmentItem->id,
                        'production_order'   => $shipmentItem->production_order_id,
                        'allocated_quantity' => round($allocation->allocated_quantity, 3),
                        'batch_unit_cost'    => round($batchUnitCost, 2),
                        'total_batch_cost'   => round($allocatedCost, 2),
                    ];
                }

                // ب. إيرادات هذا العنصر (سعر البيع الإجمالي المخزن لحظة البيع)
                $itemRevenue = $orderItem->total_price;
                $itemProfit = $itemRevenue - $itemCost;

                // تجميع إجماليات الطلب
                $orderCost += $itemCost;
                $orderRevenue += $itemRevenue;

                // إضافة تفاصيل العنصر للتقرير
                $itemsReport[] = [
                    'item_id'                => $orderItem->item_id,
                    'item_name'              => $orderItem->item->name ?? 'غير معروف',
                    'unit'                   => $orderItem->item->unit->name ?? 'وحدة',
                    'sold_quantity'          => round($orderItem->quantity, 3),
                    'selling_price_per_unit' => round($orderItem->price_per_ton, 2), // السعر للوحدة (طن، كغ، لتر)
                    'total_item_revenue'     => round($itemRevenue, 2),
                    'total_item_cost'        => round($itemCost, 2),
                    'item_profit'            => round($itemProfit, 2),
                    'item_profit_margin'     => $itemRevenue > 0 ? round(($itemProfit / $itemRevenue) * 100, 2) . '%' : '0%',
                    'batches_details'        => $batchesUsed, // الدفعات التي كونت هذه الكمية
                ];
            }

            $orderProfit = $orderRevenue - $orderCost;

            // تجميع الإجماليات العامة
            $overallTotalCost += $orderCost;
            $overallTotalRevenue += $orderRevenue;
            $overallTotalProfit += $orderProfit;

            // إضافة تفاصيل الطلب للتقرير
            $ordersReport[] = [
                'order_id'      => $order->id,
                'customer_name' => $order->customer_name ?? 'عميل نقدي',
                'sold_at'       => Carbon::parse($order->sold_at)->format('Y-m-d H:i'),
                'order_revenue' => round($orderRevenue, 2),
                'order_cost'    => round($orderCost, 2),
                'order_profit'  => round($orderProfit, 2),
                'order_profit_margin' => $orderRevenue > 0 ? round(($orderProfit / $orderRevenue) * 100, 2) . '%' : '0%',
                'items'         => $itemsReport,
            ];
        }

        // 5. حساب هامش الربح الكلي
        $overallProfitMargin = $overallTotalRevenue > 0 
            ? round(($overallTotalProfit / $overallTotalRevenue) * 100, 2) 
            : 0;

        // 6. إرجاع التقرير الشامل
        return [
            'summary' => [
                'period' => [
                    'from' => $start->format('Y-m-d'),
                    'to'   => $end->format('Y-m-d'),
                ],
                'total_orders_count'       => count($orders),
                'overall_total_revenue'    => round($overallTotalRevenue, 2), // إجمالي المبيعات
                'overall_total_cost'       => round($overallTotalCost, 2),    // إجمالي تكلفة الإنتاج/المواد
                'overall_total_profit'     => round($overallTotalProfit, 2),  // صافي الربح
                'overall_profit_margin'    => $overallProfitMargin . '%',     // هامش الربح الكلي
            ],
            'orders' => $ordersReport
        ];
    }
    // /**
    //  * استخراج تقرير المبيعات والأرباح لفترة محددة
    //  */
    // public function getSalesAndProfitReport(?string $startDate, ?string $endDate)
    // {
    //     // 1. تحديد التواريخ (إذا لم يتم تمريرها، نأخذ آخر 30 يوم)
    //     $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
    //     $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

    //     // 2. جلب الطلبات المباعة فقط ضمن الفترة المحددة مع كافة علاقاتها
    //     // افترضنا أن حالة البيع النهائي تعبر عنها 'sold' وأن تاريخ البيع هو 'sold_at'
    //     $orders = DistributionOrder::with([
    //         'items.item.unit', 
    //         'items.allocations.shipmentItem'
    //     ])
    //     ->whereNotNull('sold_at') // نجلب الطلبات التي تم بيعها فعلاً
    //     ->whereBetween('sold_at', [$start, $end])
    //     ->get();

    //     // 3. تهيئة متغيرات الإجماليات
    //     $summary = [
    //         'total_sales_revenue' => 0, // إجمالي إيرادات المبيعات
    //         'total_cost_of_goods' => 0, // إجمالي تكلفة البضاعة المباعة
    //         'total_net_profit'    => 0, // إجمالي الربح الصافي
    //         'profit_margin_pct'   => 0, // هامش الربح كنسبة مئوية
    //         'total_orders_count'  => $orders->count(),
    //     ];

    //     $ordersDetails = [];

    //     // 4. المرور على الطلبات وحساب التفاصيل
    //     foreach ($orders as $order) {
    //         $orderSales = 0;
    //         $orderCost = 0;
    //         $itemsDetails = [];

    //         foreach ($order->items as $orderItem) {
    //             // إيراد هذا السطر من الطلب
    //             $itemSalesRevenue = $orderItem->total_price; 
    //             $itemTotalCost = 0;

    //             // حساب التكلفة الدقيقة لهذا السطر بناءً على الدفعات (الطبخات/الشحنات) التي سُحب منها
    //             foreach ($orderItem->allocations as $allocation) {
    //                 $batchUnitPrice = $allocation->shipmentItem->unit_price; // سعر تكلفة الوحدة من هذه الدفعة
    //                 $allocatedQty = $allocation->allocated_quantity;
    //                 $itemTotalCost += ($batchUnitPrice * $allocatedQty);
    //             }

    //             $itemProfit = $itemSalesRevenue - $itemTotalCost;
    //             $itemProfitMargin = $itemSalesRevenue > 0 ? ($itemProfit / $itemSalesRevenue) * 100 : 0;

    //             $orderSales += $itemSalesRevenue;
    //             $orderCost += $itemTotalCost;

    //             // تسجيل تفاصيل المادة ضمن الطلب
    //             $itemsDetails[] = [
    //                 'item_id'                => $orderItem->item_id,
    //                 'item_name'              => $orderItem->item->name,
    //                 'unit'                   => $orderItem->item->unit->name ?? 'غير محدد',
    //                 'quantity_sold'          => $orderItem->quantity,
    //                 'selling_price_per_unit' => round($orderItem->price_per_ton, 2), // السعر للوحدة الواحدة (سواء طن أو غيره)
    //                 'total_sales_price'      => round($itemSalesRevenue, 2),
    //                 'total_manufacturing_cost'=> round($itemTotalCost, 2),
    //                 'net_profit'             => round($itemProfit, 2),
    //                 'profit_margin'          => round($itemProfitMargin, 2) . '%',
    //             ];
    //         }

    //         $orderProfit = $orderSales - $orderCost;
    //         $orderProfitMargin = $orderSales > 0 ? ($orderProfit / $orderSales) * 100 : 0;

    //         // إضافة مجاميع الطلب إلى المجاميع الكلية
    //         $summary['total_sales_revenue'] += $orderSales;
    //         $summary['total_cost_of_goods'] += $orderCost;
    //         $summary['total_net_profit'] += $orderProfit;

    //         // تسجيل تفاصيل الطلب
    //         $ordersDetails[] = [
    //             'order_id'       => $order->id,
    //             'customer_name'  => $order->customer_name ?? 'عميل نقدي',
    //             'sold_at'        => Carbon::parse($order->sold_at)->format('Y-m-d H:i'),
    //             'order_sales'    => round($orderSales, 2),
    //             'order_cost'     => round($orderCost, 2),
    //             'order_profit'   => round($orderProfit, 2),
    //             'profit_margin'  => round($orderProfitMargin, 2) . '%',
    //             'items_breakdown'=> $itemsDetails
    //         ];
    //     }

    //     // 5. حساب هامش الربح الإجمالي للتقرير
    //     if ($summary['total_sales_revenue'] > 0) {
    //         $summary['profit_margin_pct'] = round(($summary['total_net_profit'] / $summary['total_sales_revenue']) * 100, 2);
    //     }

    //     return [
    //         'period' => [
    //             'from' => $start->format('Y-m-d'),
    //             'to'   => $end->format('Y-m-d'),
    //         ],
    //         'summary' => [
    //             'total_orders_count'  => $summary['total_orders_count'],
    //             'total_sales_revenue' => round($summary['total_sales_revenue'], 2),
    //             'total_cost_of_goods' => round($summary['total_cost_of_goods'], 2),
    //             'total_net_profit'    => round($summary['total_net_profit'], 2),
    //             'profit_margin'       => $summary['profit_margin_pct'] . '%',
    //         ],
    //         'orders' => $ordersDetails
    //     ];
    // }
}