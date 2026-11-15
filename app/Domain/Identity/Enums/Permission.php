<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum Permission: string
{
    case FleetView = 'fleet.view';
    case FleetManage = 'fleet.manage';
    case CrewView = 'crew.view';
    case CrewManage = 'crew.manage';
    case OperationsView = 'operations.view';
    case OperationsManage = 'operations.manage';
    case MaintenanceView = 'maintenance.view';
    case MaintenanceManage = 'maintenance.manage';
    case AuditView = 'audit.view';
}
