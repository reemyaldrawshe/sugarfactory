<?php

namespace App\Services\Warehouse;

use App\Models\Item;
use Carbon\Carbon;

class StockService
{
    /**
     * 📊 Dashboard لكل المواد
     */
    public function allItemsSummary()
    {
        $items = Item::with(['shipmentItems.batch'])->get();

        return [
            'total_items' => $items->count(),

            'items' => $items->map(function ($item) {

                $shipmentItems = $item->shipmentItems()->with('batch')->get();

                $batches = $shipmentItems
                    ->map(fn($si) => $si->batch)
                    ->filter()
                    ->values();

                $totalQuantity = $batches->sum('quantity');

                $criticalCount = 0;

                foreach ($batches as $batch) {
                    if ($this->isCritical($batch->expiry_date)) {
                        $criticalCount++;
                    }
                }

                return [
                    'item_id' => $item->id,
                    'item_name' => $item->name,

                    'total_quantity' => $totalQuantity,
                    'batches_count' => $batches->count(),

                    'nearest_expiry' => $batches->min('expiry_date'),

                    'critical_batches' => $criticalCount,

                    'stock_status' => $criticalCount > 0 ? 'حرج' : 'آمن',
                ];
            })->values(),
        ];
    }

    /**
     * 🔍 تفاصيل مادة واحدة
     */
    public function singleItemSummary(Item $item)
    {
        $shipmentItems = $item->shipmentItems()->with('batch')->get();

        $batches = $shipmentItems
            ->map(fn($si) => $si->batch)
            ->filter()
            ->values();

        $totalQuantity = $batches->sum('quantity');

        return [
            'item_id' => $item->id,
            'item_name' => $item->name,

            'total_quantity' => $totalQuantity,
            'batches_count' => $batches->count(),

            'nearest_expiry' => $batches->min('expiry_date'),

            'batches' => $shipmentItems->map(function ($si) {

                $batch = $si->batch;

                if (!$batch) return null;

                return [
                    'batch_id' => $batch->id,
                    'quantity' => $batch->quantity,
                    'price' => $batch->price,
                    'expiry_date' => $batch->expiry_date,
                    'entered_at' => $batch->entered_at,
                    'note' => $batch->note,

                    'days_left' => $this->getDaysLeft($batch->expiry_date),

                    'status' => $this->isCritical($batch->expiry_date)
                        ? 'حرج'
                        : 'آمن',
                ];
            })->filter()->values(),
        ];
    }

    /**
     * ⚠️ تحديد إذا المادة حرج (قبل 10 أيام فقط)
     */
    private function isCritical($expiryDate)
    {
        if (!$expiryDate) {
            return false;
        }

        $daysLeft = $this->getDaysLeft($expiryDate);

        // منع القيم السالبة
        if ($daysLeft < 0) {
            return false;
        }

        return $daysLeft <= 10;
    }

    /**
     * 📅 حساب الأيام المتبقية (صح 100%)
     */
    private function getDaysLeft($expiryDate)
    {
        if (!$expiryDate) return null;

        return Carbon::parse($expiryDate)
            ->diffInDays(now(), false);
    }
}