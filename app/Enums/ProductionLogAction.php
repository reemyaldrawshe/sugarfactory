<?php

namespace App\Enums;

enum ProductionLogAction: string
{
    case MANAGER_APPROVED = 'manager_approved';
    case MANAGER_REJECTED = 'manager_rejected';

    case STARTED = 'started';
    case PAUSED = 'paused';
    case RESUMED = 'resumed';
 case SENT_TO_PRODUCTION = 'sent_to_production';
    case MATERIALS_RESERVED = 'materials_reserved';

    case PRODUCTION_ADDED = 'production_added';

    case COMPLETED = 'completed';
    case CREATED = 'created';
}