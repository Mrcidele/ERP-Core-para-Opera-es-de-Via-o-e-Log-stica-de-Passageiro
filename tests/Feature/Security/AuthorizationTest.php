<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Support\Facades\Route;

const UNKNOWN_ID = '0193a5b0-7c1e-7000-8000-000000000000';

/**
 * Todas as rotas protegidas e a permissão que cada uma exige.
 *
 * @return array<string, array{string, string, Permission|null}>
 */
function protectedRoutes(): array
{
    $id = UNKNOWN_ID;

    return [
        'auth.logout' => ['POST', '/api/v1/auth/logout', null],
        'auth.me' => ['GET', '/api/v1/auth/me', null],
        'dashboard.summary' => ['GET', '/api/v1/dashboard/summary', null],
        'fleet.vehicles.index' => ['GET', '/api/v1/fleet/vehicles', Permission::FleetView],
        'fleet.vehicles.show' => ['GET', "/api/v1/fleet/vehicles/{$id}", Permission::FleetView],
        'fleet.vehicles.store' => ['POST', '/api/v1/fleet/vehicles', Permission::FleetManage],
        'fleet.vehicles.update' => ['PUT', "/api/v1/fleet/vehicles/{$id}", Permission::FleetManage],
        'fleet.vehicles.destroy' => ['DELETE', "/api/v1/fleet/vehicles/{$id}", Permission::FleetManage],
        'crew.employees.index' => ['GET', '/api/v1/crew/employees', Permission::CrewView],
        'crew.employees.store' => ['POST', '/api/v1/crew/employees', Permission::CrewManage],
        'operations.lines.index' => ['GET', '/api/v1/operations/lines', Permission::OperationsView],
        'operations.lines.store' => ['POST', '/api/v1/operations/lines', Permission::OperationsManage],
        'operations.lines.update' => ['PUT', "/api/v1/operations/lines/{$id}", Permission::OperationsManage],
        'operations.lines.destroy' => ['DELETE', "/api/v1/operations/lines/{$id}", Permission::OperationsManage],
        'operations.schedule-timeline' => ['GET', '/api/v1/operations/schedule-timeline?date=2026-11-20', Permission::OperationsView],
        'operations.trips.export' => ['GET', '/api/v1/operations/trips/export', Permission::OperationsView],
        'operations.trips.index' => ['GET', '/api/v1/operations/trips', Permission::OperationsView],
        'operations.trips.store' => ['POST', '/api/v1/operations/trips', Permission::OperationsManage],
        'operations.trips.start' => ['POST', "/api/v1/operations/trips/{$id}/start", Permission::OperationsManage],
        'operations.trips.complete' => ['POST', "/api/v1/operations/trips/{$id}/complete", Permission::OperationsManage],
        'operations.trips.cancel' => ['POST', "/api/v1/operations/trips/{$id}/cancel", Permission::OperationsManage],
        'maintenance.orders.index' => ['GET', '/api/v1/maintenance/orders', Permission::MaintenanceView],
        'fuel.logs.index' => ['GET', '/api/v1/fuel/logs', Permission::FleetView],
        'fuel.logs.store' => ['POST', '/api/v1/fuel/logs', Permission::FleetManage],
        'maintenance.orders.export' => ['GET', '/api/v1/maintenance/orders/export', Permission::MaintenanceView],
        'maintenance.mechanics.index' => ['GET', '/api/v1/maintenance/mechanics', Permission::MaintenanceView],
        'maintenance.orders.store' => ['POST', '/api/v1/maintenance/orders', Permission::MaintenanceManage],
        'maintenance.orders.update' => ['PATCH', "/api/v1/maintenance/orders/{$id}", Permission::MaintenanceManage],
        'audit-logs.index' => ['GET', '/api/v1/audit-logs', Permission::AuditView],
    ];
}

it('lists in this test every API route except the public ones (login and health)', function (): void {
    $registered = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/'))
        ->map(fn ($route): ?string => $route->getName())
        ->reject(fn (?string $name): bool => in_array($name, ['auth.login', 'health'], true))
        ->values()
        ->all();

    // Uma rota nova sem entrada aqui (e portanto sem teste de proteção) quebra este teste.
    expect($registered)->toEqualCanonicalizing(array_keys(protectedRoutes()));
});

it('returns 401 without a token on every protected route', function (string $method, string $uri): void {
    $this->json($method, $uri)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Não autenticado. Entre no sistema para continuar.']);
})->with(fn (): array => array_map(fn (array $route): array => [$route[0], $route[1]], protectedRoutes()));

it('returns 401 as JSON even without the Accept header', function (): void {
    $this->get('/api/v1/fleet/vehicles')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'Não autenticado. Entre no sistema para continuar.']);
});

it('allows or denies every route for every role according to the access matrix', function (UserRole $role): void {
    actingAsRole($role);

    foreach (protectedRoutes() as $name => [$method, $uri, $permission]) {
        $response = $this->json($method, $uri);
        $allowed = $permission === null || $role->can($permission);

        if ($allowed) {
            // Passou pela autorização: o que vier depois (200, 404, 422) é do próprio endpoint.
            expect($response->getStatusCode())->not->toBeIn([401, 403], "{$role->value} deveria acessar {$name}");
        } else {
            expect($response->getStatusCode())->toBe(403, "{$role->value} não deveria acessar {$name}");
            expect($response->json())->toBe(['message' => 'Você não tem permissão para executar esta ação.']);
        }
    }
})->with(UserRole::cases());

it('forbids a mechanic supervisor from scheduling a trip', function (): void {
    actingAsRole(UserRole::MechanicSupervisor);

    $this->postJson('/api/v1/operations/trips', [
        'line_id' => LineModel::factory()->create()->id,
        'vehicle_id' => VehicleModel::factory()->create(['status' => 'active'])->id,
        'driver_id' => EmployeeModel::factory()->create()->id,
        'departure_time' => now()->addDays(3)->toAtomString(),
        'arrival_time' => now()->addDays(3)->addHours(6)->toAtomString(),
    ])->assertForbidden();

    $this->assertDatabaseCount('trips', 0);
});

it('forbids a mechanic supervisor from deleting a vehicle', function (): void {
    actingAsRole(UserRole::MechanicSupervisor);
    $vehicle = VehicleModel::factory()->create();

    $this->deleteJson("/api/v1/fleet/vehicles/{$vehicle->id}")->assertForbidden();

    $this->assertNotSoftDeleted('vehicles', ['id' => $vehicle->id]);
});

it('forbids a dispatcher from opening a maintenance order', function (): void {
    actingAsRole(UserRole::Dispatcher);
    $vehicle = VehicleModel::factory()->create(['status' => 'active']);

    $this->postJson('/api/v1/maintenance/orders', [
        'vehicle_id' => $vehicle->id,
        'mechanic_id' => EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create()->id,
        'type' => 'corrective',
        'description' => 'Reparo no ar-condicionado',
    ])->assertForbidden();

    $this->assertDatabaseCount('maintenance_orders', 0);
    expect($vehicle->fresh()->status->value)->toBe('active');
});

it('forbids a dispatcher from closing a maintenance order', function (): void {
    actingAsRole(UserRole::Dispatcher);
    $order = MaintenanceOrderModel::factory()->create();

    $this->patchJson("/api/v1/maintenance/orders/{$order->id}", ['status' => 'canceled'])->assertForbidden();

    $this->assertDatabaseHas('maintenance_orders', ['id' => $order->id, 'status' => 'open']);
});

it('lets each role do its own job', function (): void {
    $vehicle = VehicleModel::factory()->create(['status' => 'active']);
    $mechanic = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['name' => 'Maria Souza']);

    // O supervisor de oficina não enxerga a tripulação, mas escolhe o mecânico e abre a ordem.
    actingAsRole(UserRole::MechanicSupervisor);
    $this->getJson('/api/v1/crew/employees')->assertForbidden();
    $this->getJson('/api/v1/maintenance/mechanics')
        ->assertOk()
        ->assertExactJson(['data' => [['id' => $mechanic->id, 'name' => 'Maria Souza']]]);
    $this->postJson('/api/v1/maintenance/orders', [
        'vehicle_id' => $vehicle->id,
        'mechanic_id' => $mechanic->id,
        'type' => 'preventive',
        'description' => 'Troca de óleo e filtros',
    ])->assertCreated();

    // O gestor de frota exclui veículo, mas não agenda viagem.
    actingAsRole(UserRole::FleetManager);
    $this->deleteJson('/api/v1/fleet/vehicles/'.VehicleModel::factory()->create()->id)->assertNoContent();
    $this->postJson('/api/v1/operations/trips', [])->assertForbidden();

    // O despachante consulta frota e tripulação para agendar, mas não cadastra funcionário.
    actingAsRole(UserRole::Dispatcher);
    $this->getJson('/api/v1/fleet/vehicles')->assertOk();
    $this->getJson('/api/v1/crew/employees')->assertOk();
    $this->postJson('/api/v1/crew/employees', [])->assertForbidden();
});

it('ignores a role sent in the request body', function (): void {
    $user = actingAsRole(UserRole::Dispatcher);

    $this->postJson('/api/v1/maintenance/orders', ['role' => 'admin'])->assertForbidden();
    $this->getJson('/api/v1/audit-logs?role=admin')->assertForbidden();

    expect($user->fresh()->role)->toBe(UserRole::Dispatcher);
});

it('sends the security headers on API and page responses', function (string $uri): void {
    $this->get($uri)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
})->with(['/api/v1/fleet/vehicles', '/', '/login', '/up']);

it('limits the API to 120 requests per minute per user', function (): void {
    actingAsRole(UserRole::Admin);

    foreach (range(1, 120) as $request) {
        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    $this->getJson('/api/v1/auth/me')->assertTooManyRequests();

    // O limite é por usuário: outro usuário não é afetado.
    actingAsRole(UserRole::Admin);
    $this->getJson('/api/v1/auth/me')->assertOk();
});
