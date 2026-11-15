<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case FleetManager = 'fleet_manager';
    case MechanicSupervisor = 'mechanic_supervisor';

    /**
     * Matriz de acesso: o que cada papel pode fazer.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Dispatcher => [
                Permission::FleetView,
                Permission::CrewView,
                Permission::OperationsView,
                Permission::OperationsManage,
            ],
            self::FleetManager => [
                Permission::FleetView,
                Permission::FleetManage,
                Permission::CrewView,
                Permission::OperationsView,
                Permission::MaintenanceView,
                Permission::MaintenanceManage,
            ],
            self::MechanicSupervisor => [
                Permission::FleetView,
                Permission::MaintenanceView,
                Permission::MaintenanceManage,
            ],
        };
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
