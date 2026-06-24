<?php

namespace App\Services\Production;
use App\Enums\ProductionStatusEnum;
use App\Models\ProductionOrder;

class ProductionReportService
{
    /*
    |--------------------------------------------------------------------------
    | All Orders
    |--------------------------------------------------------------------------
    */

    public function allOrders()
    {
        return ProductionOrder::with([

            'item',
            'materials.item',
            'materials.shipmentItem',
            'logs.user',
            'item.section',
            'materials.item.section',

        ])
            ->latest()
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Material Requests
    |--------------------------------------------------------------------------
    */

    public function materialRequests()
    {
        return [

            'new_requests' => ProductionOrder::with([
                'item.section'
            ])
                ->where(
                    'status',
                    'approved_by_manager'
                )
                ->latest()
                ->get(),

            'preparing' => ProductionOrder::with([
                'item.section',
                'materials.item.section',
                'materials.shipmentItem'
            ])
                ->where(
                    'status',
                    'materials_reserved'
                )
                ->latest()
                ->get(),

            'delivered' => ProductionOrder::with([
                'item.section',
                'materials.item.section',
                'materials.shipmentItem'
            ])
                ->whereIn('status', [
                    'sent_to_production'
                ])
                ->latest()
                ->get(),
        ];
    }


    /**
     * شاشة الإدارة العامة والمدير (رؤية شاملة لأوامر الإنتاج مع تفاصيل سحب دفعات المواد لكل أمر)
     */
    public function getAdminOrdersDashboard()
    {
        return ProductionOrder::with([
            'item',                           // المنتج النهائي
            'materials.item',                 // المواد الخام المستخدمة
            'materials.shipmentItem',         // الدفعة المحددة التي تم السحب منها وتاريخ صلاحيتها للـ FEFO
            'logs.user'                       // سجل الحركات ومن قام بها
        ])
        ->latest()
        ->get(); // تم إلغاء الباجينيشن بناء على رغبتك السابقة ليظهر كل شيء بالواجهة
    }

    /**
     * شاشة أمين المستودع (مقسمة لـ طلبات جديدة تحتاج تحضير، وطلبات محجوزة جاهزة للتسليم)
     */
    public function getWarehouseTasks()
    {
        return [
            // 1. طلبات وافق عليها المدير وعلى المستودع كبس زر "تحضير FEFO" لتوزيع الدفعات
            'pending_preparation' => ProductionOrder::with(['item'])
                ->where('status', ProductionStatusEnum::APPROVED_BY_MANAGER->value)
                ->latest()
                ->get(),

            // 2. طلبات تم تجهيز دفعاتها وبانتظار خروجها الفعلي لخطوط الإنتاج (تأكيد الصرف)
            'ready_for_delivery' => ProductionOrder::with([
                    'item',
                    'materials.item',
                    'materials.shipmentItem'
                ])
                ->where('status', ProductionStatusEnum::MATERIALS_RESERVED->value)
                ->latest()
                ->get(),
        ];
    }

    /**
     * شاشة إدارة خطوط الإنتاج (الطلبات الجاهزة للاستلام، والطلبات التي يتم تصنيعها حالياً)
     */
    public function getProductionTasks()
    {
        return [
            // بانتظار تأكيد استلام المواد والبدء
            'ready_to_receive' => ProductionOrder::with(['item', 'materials.item'])
                ->where('status', ProductionStatusEnum::SENT_TO_PRODUCTION->value)
                ->latest()
                ->get(),

            // قيد العمل داخل المصنع حالياً
            'currently_in_production' => ProductionOrder::with(['item'])
                ->where('status', ProductionStatusEnum::IN_PRODUCTION->value)
                ->latest()
                ->get(),
        ];
    }
}
