<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\FuelLogModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->travelTo('2026-11-13 10:00:00');
    $this->admin = actingAsRole(UserRole::Admin);
    $this->bus = VehicleModel::factory()->create(['plate' => 'ABC1D23', 'current_mileage' => 100_000, 'status' => 'active']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fuelPayload(array $overrides = []): array
{
    return [
        'vehicle_id' => test()->bus->id,
        'odometer_km' => 100_000,
        'liters_milliliters' => 200_000,
        'total_cost_cents' => 119_800,
        'station_name' => 'Posto Graal Ourinhos',
        'fueled_at' => '2026-11-10T08:00:00-03:00',
        ...$overrides,
    ];
}

it('records a fuel log with who recorded it and no efficiency on the first one', function (): void {
    $response = $this->postJson('/api/v1/fuel/logs', fuelPayload(['station_name' => '  Posto Graal Ourinhos ']))->assertCreated();

    $response->assertExactJson(['data' => [
        'id' => $response->json('data.id'),
        'odometer_km' => 100_000,
        'liters_milliliters' => 200_000,
        'total_cost_cents' => 119_800,
        'station_name' => 'Posto Graal Ourinhos',
        'fueled_at' => '2026-11-10T11:00:00+00:00',
        'distance_km' => null,
        'km_per_liter' => null,
        'low_efficiency_alert' => false,
        'vehicle' => ['id' => $this->bus->id, 'plate' => 'ABC1D23'],
        'recorded_by' => ['id' => $this->admin->id, 'name' => $this->admin->name],
    ]]);

    $this->assertDatabaseHas('fuel_logs', ['id' => $response->json('data.id'), 'recorded_by_user_id' => $this->admin->id, 'fueled_at' => '2026-11-10 11:00:00+00']);
});

it('computes km per liter between two fuel logs of the same vehicle', function (): void {
    $this->postJson('/api/v1/fuel/logs', fuelPayload())->assertCreated();
    // Abastecimento de outro veículo, com hodômetro bem diferente, não interfere.
    $other = VehicleModel::factory()->create(['current_mileage' => 5_000]);
    $this->postJson('/api/v1/fuel/logs', fuelPayload(['vehicle_id' => $other->id, 'odometer_km' => 5_000, 'fueled_at' => '2026-11-10T20:00:00-03:00']))->assertCreated();

    // 630 km com 210 L: 3 km/L.
    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 100_630, 'liters_milliliters' => 210_000, 'fueled_at' => '2026-11-11T08:00:00-03:00']))
        ->assertCreated()
        ->assertJsonPath('data.distance_km', 630)
        ->assertJsonPath('data.km_per_liter', 3)
        ->assertJsonPath('data.low_efficiency_alert', false);

    // 1.000 km com 350,5 L: 2,85 km/L.
    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 101_630, 'liters_milliliters' => 350_500, 'fueled_at' => '2026-11-12T08:00:00-03:00']))
        ->assertCreated()
        ->assertJsonPath('data.km_per_liter', 2.85);
});

it('raises the low efficiency alert below 2.2 km/L', function (int $distance, float|int $kmPerLiter, bool $alert): void {
    $this->postJson('/api/v1/fuel/logs', fuelPayload())->assertCreated();

    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 100_000 + $distance, 'liters_milliliters' => 100_000, 'fueled_at' => '2026-11-11T08:00:00-03:00']))
        ->assertCreated()
        ->assertJsonPath('data.km_per_liter', $kmPerLiter)
        ->assertJsonPath('data.low_efficiency_alert', $alert);
})->with([
    'consumo alto' => [150, 1.5, true],
    'logo abaixo do limite' => [219, 2.19, true],
    'no limite' => [220, 2.2, false],
    'dentro do padrão' => [310, 3.1, false],
]);

it('refuses an odometer lower than the last fuel log of the vehicle', function (): void {
    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 100_500]))->assertCreated();

    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 100_499, 'fueled_at' => '2026-11-11T08:00:00-03:00']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['odometer_km' => 'O hodômetro não pode ser menor que o do último abastecimento deste veículo (100500 km).']);

    $this->assertDatabaseCount('fuel_logs', 1);
    $this->assertDatabaseCount('audit_logs', 1);

    // Igual ao último é aceito (veículo abastecido de novo sem rodar).
    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 100_500, 'fueled_at' => '2026-11-11T08:00:00-03:00']))
        ->assertCreated()
        ->assertJsonPath('data.km_per_liter', null);
});

it('refuses a fuel log out of chronological order, in the future or for an unknown vehicle', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/fuel/logs', fuelPayload())->assertCreated();

    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 100_400, ...$overrides]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('fuel_logs', 1);
})->with([
    'antes do último' => [['fueled_at' => '2026-11-10T07:59:00-03:00'], 'fueled_at'],
    'no futuro' => [['fueled_at' => '2026-11-13T07:01:00-03:00'], 'fueled_at'],
    'veículo inexistente' => [['vehicle_id' => '0193a5b0-7c1e-7000-8000-000000000000', 'fueled_at' => '2026-11-11T08:00:00-03:00'], 'vehicle_id'],
]);

it('refuses a fuel log for a deleted vehicle', function (): void {
    $this->bus->delete();

    $this->postJson('/api/v1/fuel/logs', fuelPayload())->assertUnprocessable()->assertJsonValidationErrors('vehicle_id');
});

it('rejects invalid input', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/fuel/logs', fuelPayload($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('fuel_logs', 0);
})->with([
    'sem veículo' => [['vehicle_id' => null], 'vehicle_id'],
    'veículo fora do formato' => [['vehicle_id' => 'abc'], 'vehicle_id'],
    'hodômetro negativo' => [['odometer_km' => -1], 'odometer_km'],
    'hodômetro fracionado' => [['odometer_km' => 100.5], 'odometer_km'],
    'volume zero' => [['liters_milliliters' => 0], 'liters_milliliters'],
    'volume acima do teto' => [['liters_milliliters' => 5_000_001], 'liters_milliliters'],
    'custo negativo' => [['total_cost_cents' => -1], 'total_cost_cents'],
    'sem posto' => [['station_name' => ' '], 'station_name'],
    'data sem fuso' => [['fueled_at' => '2026-11-10 08:00:00'], 'fueled_at'],
    'sem data' => [['fueled_at' => null], 'fueled_at'],
]);

it('takes the vehicle mileage up to the odometer, and never down', function (int $odometer, int $expected): void {
    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => $odometer]))->assertCreated();

    expect($this->bus->refresh()->current_mileage)->toBe($expected);
    $this->getJson("/api/v1/fleet/vehicles/{$this->bus->id}")->assertOk()->assertJsonPath('data.current_mileage', $expected);
})->with([
    'hodômetro à frente do cadastro' => [100_820, 100_820],
    'hodômetro atrás do cadastro' => [99_500, 100_000],
]);

it('flags the preventive maintenance once the odometer passes the scheduled mileage', function (): void {
    $this->bus->update(['next_preventive_maintenance_km' => 100_500]);

    $this->postJson('/api/v1/fuel/logs', fuelPayload(['odometer_km' => 100_600]))->assertCreated();

    $this->getJson('/api/v1/fleet/vehicles?needs_maintenance=1')->assertOk()->assertJsonPath('data.0.plate', 'ABC1D23');
});

it('audits the fuel log', function (): void {
    $id = $this->postJson('/api/v1/fuel/logs', fuelPayload())->json('data.id');

    $log = AuditLogModel::query()->sole();

    expect([$log->action, $log->auditable_type, $log->auditable_id, $log->old_values, $log->user_id])
        ->toBe(['fuel_log.recorded', 'fuel_log', $id, null, $this->admin->id])
        // O JSONB não preserva a ordem das chaves.
        ->and($log->new_values)->toEqual([
            'vehicle_id' => $this->bus->id,
            'odometer_km' => 100_000,
            'liters_milliliters' => 200_000,
            'total_cost_cents' => 119_800,
            'station_name' => 'Posto Graal Ourinhos',
            'fueled_at' => '2026-11-10T11:00:00+00:00',
        ]);

    $this->getJson('/api/v1/audit-logs?search=abastecimento')->assertOk()->assertJsonPath('data.0.action_label', 'Abastecimento registrado');
});

it('lists the fuel logs most recent first, paged, each with its own efficiency', function (): void {
    $other = VehicleModel::factory()->create(['plate' => 'XYZ9K88']);

    foreach (range(1, 9) as $day) {
        FuelLogModel::factory()->create([
            'vehicle_id' => $this->bus->id, 'recorded_by_user_id' => $this->admin->id, 'station_name' => 'Posto Graal Ourinhos',
            'odometer_km' => 100_000 + $day * 300, 'liters_milliliters' => 100_000, 'fueled_at' => sprintf('2026-11-%02d 12:00:00+00', $day),
        ]);
        FuelLogModel::factory()->create([
            'vehicle_id' => $other->id, 'recorded_by_user_id' => $this->admin->id, 'station_name' => 'Garagem Central',
            'odometer_km' => 40_000 + $day * 150, 'liters_milliliters' => 100_000, 'fueled_at' => sprintf('2026-11-%02d 18:00:00+00', $day),
        ]);
    }

    $this->getJson('/api/v1/fuel/logs')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2, 'per_page' => 15, 'total' => 18])
        ->assertJsonPath('data.0.vehicle.plate', 'XYZ9K88')
        ->assertJsonPath('data.0.fueled_at', '2026-11-09T18:00:00+00:00')
        ->assertJsonPath('data.0.km_per_liter', 1.5)
        ->assertJsonPath('data.0.low_efficiency_alert', true)
        ->assertJsonPath('data.1.vehicle.plate', 'ABC1D23')
        ->assertJsonPath('data.1.km_per_liter', 3)
        ->assertJsonPath('data.1.low_efficiency_alert', false);

    // O primeiro abastecimento de cada veículo, na última página, não tem rendimento.
    $this->getJson('/api/v1/fuel/logs?page=2')->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.1.km_per_liter', null)
        ->assertJsonPath('data.2.km_per_liter', null)
        ->assertJsonPath('data.0.km_per_liter', 3);

    $this->getJson('/api/v1/fuel/logs?search=xyz-9k88')->assertOk()->assertJsonPath('meta.total', 9);
    $this->getJson('/api/v1/fuel/logs?search=garagem')->assertOk()->assertJsonPath('meta.total', 9);
    $this->getJson('/api/v1/fuel/logs?search=inexistente')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/fuel/logs?status=open')->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('ignores a deleted fuel log when computing the efficiency of the next one', function (): void {
    $first = FuelLogModel::factory()->create(['vehicle_id' => $this->bus->id, 'odometer_km' => 100_000, 'fueled_at' => '2026-11-01 12:00:00+00']);
    $mistake = FuelLogModel::factory()->create(['vehicle_id' => $this->bus->id, 'odometer_km' => 100_590, 'fueled_at' => '2026-11-02 12:00:00+00']);
    FuelLogModel::factory()->create(['vehicle_id' => $this->bus->id, 'odometer_km' => 100_600, 'liters_milliliters' => 200_000, 'fueled_at' => '2026-11-03 12:00:00+00']);

    $this->getJson('/api/v1/fuel/logs')->assertOk()->assertJsonPath('data.0.distance_km', 10);

    $mistake->delete();

    $this->getJson('/api/v1/fuel/logs')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.distance_km', 600)
        ->assertJsonPath('data.0.km_per_liter', 3)
        ->assertJsonPath('data.1.id', $first->id);
});

it('lets who can see the fleet list the fuel logs and only who manages it record them', function (UserRole $role, int $list, int $record): void {
    actingAsRole($role);

    $this->getJson('/api/v1/fuel/logs')->assertStatus($list);
    $this->postJson('/api/v1/fuel/logs', fuelPayload())->assertStatus($record);
})->with([
    [UserRole::FleetManager, 200, 201],
    [UserRole::Dispatcher, 200, 403],
    [UserRole::MechanicSupervisor, 200, 403],
]);

it('shows the fuel spent since the first day of the month on the dashboard', function (): void {
    $log = fn (string $at, int $cost, int $milliliters) => FuelLogModel::factory()->create([
        'vehicle_id' => $this->bus->id, 'fueled_at' => $at, 'total_cost_cents' => $cost, 'liters_milliliters' => $milliliters,
    ]);

    $log('2026-10-31 23:59:59+00', 999_999, 900_000);
    $log('2026-11-01 00:00:00+00', 120_000, 200_000);
    $log('2026-11-12 15:00:00+00', 95_050, 150_500);

    $this->getJson('/api/v1/dashboard/summary')->assertOk()->assertJsonPath('data.fuel', [
        'month_start' => '2026-11-01T00:00:00+00:00',
        'month_cost_cents' => 215_050,
        'month_liters_milliliters' => 350_500,
        'month_logs' => 2,
    ]);

    // Sem fleet.view o bloco não sai. (Nenhum papel atual fica sem ela; o administrador é o caso de controle.)
    $this->getJson('/api/v1/dashboard/summary')->assertOk()->assertJsonStructure(['data' => ['fuel' => ['month_cost_cents']]]);
});

it('refuses at the database a fuel log with invalid numbers', function (array $values): void {
    expect(fn () => FuelLogModel::factory()->create($values))->toThrow(QueryException::class);
})->with([
    'hodômetro negativo' => [['odometer_km' => -1]],
    'volume zero' => [['liters_milliliters' => 0]],
    'custo negativo' => [['total_cost_cents' => -1]],
]);
