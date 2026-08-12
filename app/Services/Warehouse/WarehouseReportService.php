<?php

namespace App\Services\Warehouse;

use Carbon\Carbon;
use App\Models\ItemTrackingLog;
use App\Models\DemolishOrder;
use App\Models\InventoryAudit;
use App\Models\ShipmentItem;

class WarehouseReportService
{
    /**
     * المولد الرئيسي للتقارير
     */
    public function generateReport(string $type, ?string $fromDate, ?string $toDate): array
    {
        // 1. تحديد تواريخ البحث (إذا لم تُرسل، نجلب آخر 30 يوم)
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $toDate ? Carbon::parse($toDate)->endOfDay() : now()->endOfDay();

        // 2. توجيه الطلب لنوع التقرير المناسب
        return match ($type) {
            'movements' => $this->getMovementsReport($from, $to),
            'demolish'  => $this->getDemolishReport($from, $to),
            'audits'    => $this->getAuditsReport($from, $to),
            'expiries'  => $this->getExpiriesReport($from, $to), // تقرير الصلاحيات قد لا يعتمد كلياً على التاريخ ولكن نمرره
            default     => throw new \InvalidArgumentException("نوع التقرير غير مدعوم: {$type}")
        };
    }

    /**
     * 1. تقرير حركة المواد (الوارد، المنصرف، الخ)
     */
    private function getMovementsReport($from, $to): array
    {
        $data = ItemTrackingLog::with(['item', ])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'date' => $log->created_at->format('Y-m-d H:i'),
                    'type' => $log->type,
                    'item_name' => $log->item_name,
                    'quantity' => $log->quantity,
                    'sender' => $log->sent_from_user_name,
                    'receiver' => $log->sent_to_user_name,
                ];
            });

        return $this->formatResponse(
            title: 'تقرير حركة المستودع',
            columns: [
                ['key' => 'date', 'label' => 'التاريخ'],
                ['key' => 'type', 'label' => 'نوع الحركة'],
                ['key' => 'item_name', 'label' => 'المادة'],
                ['key' => 'quantity', 'label' => 'الكمية'],
                ['key' => 'sender', 'label' => 'المرسل'],
                ['key' => 'receiver', 'label' => 'المستلم'],
            ],
            data: $data,
            summary: [
                ['label' => 'إجمالي الحركات', 'value' => $data->count()],
                ['label' => 'كمية الوارد', 'value' => $data->where('type', 'توريد')->sum('quantity')],
                ['label' => 'كمية المنصرف', 'value' => $data->whereIn('type', ['صرف', 'بيع وتوزيع'])->sum('quantity')],
            ]
        );
    }

    /**
     * 2. تقرير الإتلاف
     */
    private function getDemolishReport($from, $to): array
    {
        $data = DemolishOrder::with(['item', 'creator', 'approver'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'date' => $order->created_at->format('Y-m-d'),
                    'item_name' => $order->item->name ?? 'غير معروف',
                    'quantity' => $order->quantity,
                    'reason' => $order->reason,
                    'status' => $this->translateStatus($order->status),
                    'created_by' => $order->creator->name ?? '-',
                ];
            });

        return $this->formatResponse(
            title: 'تقرير التوالف والإتلاف',
            columns: [
                ['key' => 'date', 'label' => 'التاريخ'],
                ['key' => 'item_name', 'label' => 'المادة'],
                ['key' => 'quantity', 'label' => 'الكمية'],
                ['key' => 'reason', 'label' => 'السبب'],
                ['key' => 'status', 'label' => 'الحالة'],
                ['key' => 'created_by', 'label' => 'بواسطة'],
            ],
            data: $data,
            summary: [
                ['label' => 'إجمالي طلبات الإتلاف', 'value' => $data->count()],
                ['label' => 'الطلبات المعتمدة', 'value' => $data->where('status', 'معتمد')->count()],
            ]
        );
    }

    /**
     * 3. تقرير عمليات الجرد
     */
    private function getAuditsReport($from, $to): array
    {
        $data = InventoryAudit::with('creator')
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get()
            ->map(function ($audit) {
                return [
                    'id' => $audit->id,
                    'code' => $audit->code,
                    'date' => $audit->created_at->format('Y-m-d'),
                    'status' => $this->translateStatus($audit->status),
                    'created_by' => $audit->creator->name ?? '-',
                ];
            });

        return $this->formatResponse(
            title: 'تقرير عمليات الجرد',
            columns: [
                ['key' => 'code', 'label' => 'رقم الجرد'],
                ['key' => 'date', 'label' => 'التاريخ'],
                ['key' => 'status', 'label' => 'الحالة'],
                ['key' => 'created_by', 'label' => 'أمين المستودع'],
            ],
            data: $data,
            summary: [
                ['label' => 'إجمالي الجرد', 'value' => $data->count()],
            ]
        );
    }

    /**
     * 4. تقرير تواريخ الصلاحية (يعتمد على الدفعات)
     */
    private function getExpiriesReport($from, $to): array
    {
        // هنا نجلب الدفعات التي تم إدخالها في هذا التاريخ (أو يمكن تجاهل التاريخ وجلب كل شيء قريب الانتهاء)
        $data = ShipmentItem::with(['item.section'])
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'item_name' => $batch->item->name ?? '-',
                    'section' => $batch->item->section->name ?? '-',
                    'quantity_remaining' => $batch->quantity_received,
                    'expiry_date' => $batch->expiry_date->format('Y-m-d'),
                    // نستخدم الـ accessor الذي قمت أنت بإنشائه مسبقاً
                    'status' => $this->translateExpiryStatus($batch->expiry_status), 
                ];
            });

        return $this->formatResponse(
            title: 'تقرير الصلاحيات والدفعات',
            columns: [
                ['key' => 'item_name', 'label' => 'المادة'],
                ['key' => 'section', 'label' => 'القسم'],
                ['key' => 'quantity_remaining', 'label' => 'الكمية المتبقية'],
                ['key' => 'expiry_date', 'label' => 'تاريخ الانتهاء'],
                ['key' => 'status', 'label' => 'حالة الصلاحية'],
            ],
            data: $data,
            summary: [
                ['label' => 'منتهية الصلاحية', 'value' => $data->where('status', 'منتهي')->count()],
                ['label' => 'قريب الانتهاء', 'value' => $data->where('status', 'قريب الانتهاء')->count()],
            ]
        );
    }

    /**
     * دالة مساعدة لتوحيد شكل الاستجابة للـ Frontend
     */
    private function formatResponse(string $title, array $columns, $data, array $summary): array
    {
        return [
            'meta' => [
                'title' => $title,
                'generated_at' => now()->format('Y-m-d H:i:s'),
            ],
            'columns' => $columns, // لكي يبني الـ Frontend الجدول ديناميكياً
            'summary_cards' => $summary, // بطاقات إحصائية أعلى الجدول
            'data' => $data, // بيانات الجدول الحقيقية
        ];
    }

    // دوال للترجمة
    private function translateStatus($status) {
        return match($status) {
            'pending' => 'قيد الانتظار',
            'approved' => 'معتمد',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
            default => $status
        };
    }

    private function translateExpiryStatus($status) {
        return match($status) {
            'expired' => 'منتهي',
            'expiring_soon' => 'قريب الانتهاء',
            'good' => 'صالح',
            'no_expiry' => 'بدون صلاحية',
            default => $status
        };
    }
}