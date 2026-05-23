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

    case COMPLETED = 'completed';
}
