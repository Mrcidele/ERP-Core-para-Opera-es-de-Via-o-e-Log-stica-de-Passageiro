<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;

beforeEach(function (): void {
    $this->travelTo('2026-11-13 10:00:00');
    actingAsRole(UserRole::Admin);
});

function vehicleWith(string $status, string $plate, int $mileage = 100_000, ?int $nextKm = null): VehicleModel
{
    return VehicleModel::factory()->create([
        'status' => $status,
        'plate' => $plate,
        'current_mileage' => $mileage,
        'next_preventive_maintenance_km' => $nextKm,
    ]);
}

function driverWith(string $name, ?string $expiration, string $status = 'active'): EmployeeModel
{
    return EmployeeModel::factory()->create(['name' => $name, 'cnh_expiration_date' => $expiration, 'status' => $status]);
}

function tripWith(string $status, string $departure, string $arrival, ?VehicleModel $vehicle = null, ?EmployeeModel $driver = null, ?LineModel $line = null): TripModel
{
    return TripModel::query()->create([
        'line_id' => ($line ?? LineModel::factory()->create())->id,
        'vehicle_id' => ($vehicle ?? VehicleModel::factory()->create())->id,
        'driver_id' => ($driver ?? EmployeeModel::factory()->create())->id,
        'departure_time' => $departure.'+00',
        'arrival_time' => $arrival.'+00',
        'status' => $status,
    ]);
}

it('returns an empty but complete summary when there is no data', function (): void {
    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertExactJson(['data' => [
            'generated_at' => '2026-11-13T10:00:00+00:00',
            'fleet' => ['total' => 0, 'active' => 0, 'maintenance' => 0, 'inactive' => 0, 'availability_rate' => 0, 'preventive_maintenance_due' => []],
            'operations' => ['in_progress_now' => 0, 'scheduled_next_24h' => 0, 'next_trips' => []],
            'crew' => ['expiring_licenses' => []],
            'maintenance' => ['completed_last_30_days' => ['count' => 0, 'total_cost_cents' => 0], 'open' => 0, 'in_progress' => 0],
            'fuel' => ['month_start' => '2026-11-01T00:00:00+00:00', 'month_cost_cents' => 0, 'month_liters_milliliters' => 0, 'month_logs' => 0],
        ]]);
});

it('summarizes the fleet and lists the vehicles due for preventive maintenance, most overdue first', function (): void {
    vehicleWith('active', 'AAA1A11', 100_000, 120_000);
    vehicleWith('active', 'BBB2B22', 100_000, 100_000);
    vehicleWith('active', 'CCC3C33', 100_000, null);
    $overdue = vehicleWith('maintenance', 'DDD4D44', 100_000, 95_000);
    vehicleWith('inactive', 'EEE5E55');
    vehicleWith('active', 'FFF6F66', 500_000, 1)->delete();

    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.fleet.total', 5)
        ->assertJsonPath('data.fleet.active', 3)
        ->assertJsonPath('data.fleet.maintenance', 1)
        ->assertJsonPath('data.fleet.inactive', 1)
        ->assertJsonPath('data.fleet.availability_rate', 60)
        ->assertJsonCount(2, 'data.fleet.preventive_maintenance_due')
        ->assertJsonPath('data.fleet.preventive_maintenance_due.0', [
            'id' => $overdue->id,
            'plate' => 'DDD4D44',
            'current_mileage' => 100_000,
            'next_preventive_maintenance_km' => 95_000,
            'needs_preventive_maintenance' => true,
        ])
        ->assertJsonPath('data.fleet.preventive_maintenance_due.1.plate', 'BBB2B22');
});

it('counts trips in progress and scheduled for the next 24 hours, and lists the next five', function (): void {
    $line = LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => 'Ourinhos']);
    $truck = vehicleWith('active', 'ABC1D23');
    $driver = driverWith('João da Silva', '2030-01-01');

    tripWith('in_progress', '2026-11-13 06:00', '2026-11-13 12:00');
    tripWith('in_progress', '2026-11-13 08:00', '2026-11-13 14:00');
    tripWith('completed', '2026-11-12 06:00', '2026-11-12 12:00');
    tripWith('canceled', '2026-11-13 12:00', '2026-11-13 18:00');
    tripWith('scheduled', '2026-11-13 09:00', '2026-11-13 15:00');                       // já devia ter saído: fora da fila
    $next = tripWith('scheduled', '2026-11-13 11:00', '2026-11-13 17:30', $truck, $driver, $line);
    tripWith('scheduled', '2026-11-14 10:00', '2026-11-14 16:00');                       // exatamente em 24h: conta
    tripWith('scheduled', '2026-11-14 10:01', '2026-11-14 16:00');                       // depois de 24h
    foreach (['15', '16', '17', '18'] as $day) {
        tripWith('scheduled', "2026-11-{$day} 08:00", "2026-11-{$day} 14:00");
    }
    tripWith('scheduled', '2026-11-13 10:30', '2026-11-13 12:00')->delete();

    $response = $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.operations.in_progress_now', 2)
        ->assertJsonPath('data.operations.scheduled_next_24h', 2)
        ->assertJsonCount(5, 'data.operations.next_trips')
        ->assertJsonPath('data.operations.next_trips.0', [
            'id' => $next->id,
            'departure_time' => '2026-11-13T11:00:00+00:00',
            'arrival_time' => '2026-11-13T17:30:00+00:00',
            'line' => ['origin' => 'Curitiba', 'destination' => 'Ourinhos'],
            'vehicle' => ['plate' => 'ABC1D23'],
            'driver' => ['name' => 'João da Silva'],
        ]);

    expect(array_column($response->json('data.operations.next_trips'), 'departure_time'))->toBe([
        '2026-11-13T11:00:00+00:00',
        '2026-11-14T10:00:00+00:00',
        '2026-11-14T10:01:00+00:00',
        '2026-11-15T08:00:00+00:00',
        '2026-11-16T08:00:00+00:00',
    ]);
});

it('alerts about active drivers whose license expires within 30 days or already expired', function (): void {
    $expired = driverWith('Carlos Vencido', '2026-11-10');
    driverWith('Bia Hoje', '2026-11-13');
    driverWith('Ana Limite', '2026-12-13');
    driverWith('Zé Tranquilo', '2026-12-14');
    driverWith('Lia Afastada', '2026-11-20', 'on_leave');
    driverWith('Rui Inativo', '2026-11-20', 'inactive');
    driverWith('Edu Excluído', '2026-11-20')->delete();
    EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create();

    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.crew.expiring_licenses', [
            ['employee_id' => $expired->id, 'name' => 'Carlos Vencido', 'cnh_expiration_date' => '2026-11-10', 'days_remaining' => -3],
            ['employee_id' => EmployeeModel::query()->where('name', 'Bia Hoje')->value('id'), 'name' => 'Bia Hoje', 'cnh_expiration_date' => '2026-11-13', 'days_remaining' => 0],
            ['employee_id' => EmployeeModel::query()->where('name', 'Ana Limite')->value('id'), 'name' => 'Ana Limite', 'cnh_expiration_date' => '2026-12-13', 'days_remaining' => 30],
        ]);
});

it('sums the maintenance orders completed in the last 30 days and counts the pending ones', function (): void {
    $order = fn (string $status, int $cost = 0, ?string $completedAt = null) => MaintenanceOrderModel::factory()->create([
        'status' => $status,
        'cost_cents' => $cost,
        'opened_at' => '2026-09-01 08:00:00+00',
        'completed_at' => $completedAt === null ? null : $completedAt.'+00',
    ]);

    $order('completed', 45_090, '2026-11-12 16:00');
    $order('completed', 100_010, '2026-10-14 10:00');      // exatamente 30 dias atrás: conta
    $order('completed', 999_999, '2026-10-14 09:59');      // um minuto antes: fora
    $order('canceled', 500);
    $order('open');
    $order('open');
    $order('in_progress');
    $order('completed', 777, '2026-11-12 16:00')->delete();

    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.maintenance', [
            'completed_last_30_days' => ['count' => 2, 'total_cost_cents' => 145_100],
            'open' => 2,
            'in_progress' => 1,
        ]);
});

it('gives each role only the blocks of the modules it can consult', function (UserRole $role, array $visible): void {
    actingAsRole($role);

    $data = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json('data');

    foreach (['fleet', 'operations', 'crew', 'maintenance'] as $block) {
        expect($data[$block] !== null)->toBe(in_array($block, $visible, true), "{$role->value}: {$block}");
    }
})->with([
    'admin' => [UserRole::Admin, ['fleet', 'operations', 'crew', 'maintenance']],
    'dispatcher' => [UserRole::Dispatcher, ['fleet', 'operations', 'crew']],
    'fleet manager' => [UserRole::FleetManager, ['fleet', 'operations', 'crew', 'maintenance']],
    'workshop supervisor' => [UserRole::MechanicSupervisor, ['fleet', 'maintenance']],
]);

it('requires authentication', function (): void {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
});
