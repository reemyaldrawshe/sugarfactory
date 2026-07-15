<?php

namespace App\Enums;

enum ProductionStatusEnum:string
{
    case PENDING = 'pending';

    case APPROVED_BY_MANAGER = 'approved_by_manager';

    case REJECTED_BY_MANAGER = 'rejected_by_manager';

    case MATERIALS_RESERVED = 'materials_reserved';

    case SENT_TO_PRODUCTION = 'sent_to_production';

    case IN_PRODUCTION = 'in_production';

    case PAUSED = 'paused';
    // case STARTED = 'started';

    case COMPLETED = 'completed';
    // حالات خاصة بالمبيعات
    case READY_FOR_SALE = 'ready_for_sale'; // المواد جاهزة للبيع
    case SOLD = 'sold'; // تم البيع تسليمها للعميل
    // الحالات الخاصة بقسم التوزيع والمبيعات (Distribution)
    case DIST_PENDING = 'dist_pending';                     // طلب توزيع جديد بانتظار المدير
    case DIST_APPROVED = 'dist_approved';                   // موافقة المدير وتحويل للمستودع
    case DIST_REJECTED = 'dist_rejected';                   // رفض الطلب من قبل الإدارة
    case DIST_MATERIALS_RESERVED = 'dist_materials_reserved'; // تم حجز الدفعات في المستودع للبيع
    case DIST_DISPATCHED = 'dist_dispatched';               // خرجت البضاعة للتسليم
    case DIST_SOLD = 'dist_sold';                           // تم التسليم النهائي والبيع الفعلي
}
