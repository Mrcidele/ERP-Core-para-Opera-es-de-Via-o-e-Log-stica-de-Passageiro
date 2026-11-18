<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserRole;

it('follows the access matrix', function (UserRole $role, array $expected): void {
    $granted = array_map(fn (Permission $permission): string => $permission->value, $role->permissions());

    expect($granted)->toEqualCanonicalizing($expected);
})->with([
    'admin' => [UserRole::Admin, [
        'fleet.view', 'fleet.manage', 'crew.view', 'crew.manage', 'operations.view', 'operations.manage',
        'maintenance.view', 'maintenance.manage', 'audit.view', 'users.manage',
    ]],
    'dispatcher' => [UserRole::Dispatcher, ['fleet.view', 'crew.view', 'operations.view', 'operations.manage']],
    'fleet manager' => [UserRole::FleetManager, [
        'fleet.view', 'fleet.manage', 'crew.view', 'operations.view', 'maintenance.view', 'maintenance.manage',
    ]],
    'mechanic supervisor' => [UserRole::MechanicSupervisor, ['fleet.view', 'maintenance.view', 'maintenance.manage']],
]);

it('answers whether a role has a permission', function (): void {
    expect(UserRole::Dispatcher->can(Permission::OperationsManage))->toBeTrue()
        ->and(UserRole::Dispatcher->can(Permission::MaintenanceManage))->toBeFalse()
        ->and(UserRole::MechanicSupervisor->can(Permission::FleetManage))->toBeFalse()
        ->and(UserRole::FleetManager->can(Permission::AuditView))->toBeFalse();
});

it('reserves audit access and crew management to the admin', function (Permission $permission): void {
    $roles = array_filter(UserRole::cases(), fn (UserRole $role): bool => $role->can($permission));

    expect(array_values($roles))->toBe([UserRole::Admin]);
})->with([Permission::AuditView, Permission::CrewManage]);
