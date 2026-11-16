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
    actingAsRole(UserRole::Admin);

    $this->line = LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => 'Ourinhos']);
    $this->bus = VehicleModel::factory()->create(['plate' => 'AAA1A11', 'status' => 'active']);
    $this->ana = EmployeeModel::factory()->create(['name' => 'Ana Lima', 'status' => 'active']);
});

function timelineTrip(string $departure, string $arrival, string $status = 'scheduled', ?VehicleModel $vehicle = null, ?EmployeeModel $driver = null): TripModel
{
    return TripModel::query()->create([
        'line_id' => test()->line->id,
        'vehicle_id' => ($vehicle ?? test()->bus)->id,
        'driver_id' => ($driver ?? test()->ana)->id,
        'departure_time' => $departure.'+00',
        'arrival_time' => $arrival.'+00',
        'status' => $status,
    ]);
}

/**
 * @return array<string, mixed>
 */
function timeline(string $date = '2026-11-20'): array
{
    return test()->getJson("/api/v1/operations/schedule-timeline?date={$date}")->assertOk()->json('data');
}

it('returns the 48 hours that start at midnight UTC of the date, by vehicle and by driver', function (): void {
    $trip = timelineTrip('2026-11-20 11:00:00', '2026-11-20 17:30:00');

    $this->getJson('/api/v1/operations/schedule-timeline?date=2026-11-20')
        ->assertOk()
        ->assertExactJson(['data' => [
            'from' => '2026-11-20T00:00:00+00:00',
            'to' => '2026-11-22T00:00:00+00:00',
            'driver_rest_hours' => 11,
            'vehicles' => [[
                'id' => $this->bus->id,
                'plate' => 'AAA1A11',
                'status' => 'active',
                'maintenance_orders' => [],
                'trips' => [$expected = [
                    'id' => $trip->id,
                    'status' => 'scheduled',
                    'departure_time' => '2026-11-20T11:00:00+00:00',
                    'arrival_time' => '2026-11-20T17:30:00+00:00',
                    // 11 horas de descanso depois da chegada.
                    'rest_until' => '2026-11-21T04:30:00+00:00',
                    'line' => ['origin' => 'Curitiba', 'destination' => 'Ourinhos'],
                    'vehicle' => ['id' => $this->bus->id, 'plate' => 'AAA1A11'],
                    'driver' => ['id' => $this->ana->id, 'name' => 'Ana Lima'],
                ]],
            ]],
            'drivers' => [[
                'id' => $this->ana->id,
                'name' => 'Ana Lima',
                'trips' => [$expected],
            ]],
        ]]);
});

it('lists every active vehicle and driver, even with nothing scheduled, in plate and name order', function (): void {
    VehicleModel::factory()->create(['plate' => 'ZZZ9Z99', 'status' => 'active']);
    VehicleModel::factory()->create(['plate' => 'BBB2B22', 'status' => 'inactive']);
    VehicleModel::factory()->create(['plate' => 'CCC3C33', 'status' => 'maintenance']);
    VehicleModel::factory()->create(['plate' => 'DDD4D44', 'status' => 'active'])->delete();
    EmployeeModel::factory()->create(['name' => 'Bruno Dias', 'status' => 'active']);
    EmployeeModel::factory()->create(['name' => 'Carla Afastada', 'status' => 'on_leave']);
    EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['name' => 'Zeca Mecânico', 'status' => 'active']);

    $data = timeline();

    expect(array_column($data['vehicles'], 'plate'))->toBe(['AAA1A11', 'ZZZ9Z99'])
        ->and(array_column($data['drivers'], 'name'))->toBe(['Ana Lima', 'Bruno Dias'])
        ->and($data['vehicles'][1]['trips'])->toBe([])
        ->and($data['drivers'][1]['trips'])->toBe([]);
});

it('includes the trips that touch the window and leaves out the canceled ones', function (): void {
    // Cada viagem com veículo e motorista próprios: o banco não aceita sobreposição nem descanso menor que 11h.
    $own = fn (string $departure, string $arrival, string $status): TripModel => timelineTrip(
        $departure,
        $arrival,
        $status,
        VehicleModel::factory()->create(['status' => 'active']),
        EmployeeModel::factory()->create(['status' => 'active']),
    );

    $before = $own('2026-11-19 10:00:00', '2026-11-19 12:00:00', 'completed');
    $endsAtStart = $own('2026-11-19 20:00:00', '2026-11-20 00:00:00', 'completed');
    $overnight = $own('2026-11-19 22:00:00', '2026-11-20 06:00:00', 'in_progress');
    $canceled = $own('2026-11-20 20:00:00', '2026-11-20 23:00:00', 'canceled');
    $crossesEnd = $own('2026-11-21 21:00:00', '2026-11-22 05:00:00', 'scheduled');
    $after = $own('2026-11-22 16:00:00', '2026-11-22 20:00:00', 'scheduled');

    $data = timeline();
    $tripsOf = fn (string $group): array => collect($data[$group])->flatMap(fn (array $row): array => array_column($row['trips'], 'id'))->all();

    // Veículo: só as viagens que ocupam algum instante da janela.
    expect($tripsOf('vehicles'))->toEqualCanonicalizing([$overnight->id, $crossesEnd->id])
        // Motorista: também a viagem já encerrada cujo descanso de 11h ainda alcança a janela.
        ->and($tripsOf('drivers'))->toEqualCanonicalizing([$endsAtStart->id, $overnight->id, $crossesEnd->id])
        ->and(array_intersect([$before->id, $canceled->id, $after->id], [...$tripsOf('vehicles'), ...$tripsOf('drivers')]))->toBe([]);

    $rest = collect($data['drivers'])->flatMap(fn (array $row): array => $row['trips'])->firstWhere('id', $endsAtStart->id);

    expect($rest['rest_until'])->toBe('2026-11-20T11:00:00+00:00')
        ->and($rest['status'])->toBe('completed');
});

it('leaves out a trip whose rest ended exactly when the window starts', function (): void {
    timelineTrip('2026-11-19 08:00:00', '2026-11-19 13:00:00', 'completed');

    expect(timeline()['drivers'][0]['trips'])->toBe([]);
});

it('shows vehicles with a pending maintenance order, with the order, even though they are not active', function (): void {
    $workshop = VehicleModel::factory()->create(['plate' => 'OFI1C11', 'status' => 'maintenance']);
    $open = MaintenanceOrderModel::factory()->create(['vehicle_id' => $workshop->id, 'type' => 'corrective', 'status' => 'open', 'opened_at' => '2026-11-18 14:00:00+00']);
    MaintenanceOrderModel::factory()->completed()->create(['vehicle_id' => $workshop->id]);
    // Ordem aberta depois da janela não aparece nela.
    $later = VehicleModel::factory()->create(['plate' => 'OFI2C22', 'status' => 'maintenance']);
    MaintenanceOrderModel::factory()->create(['vehicle_id' => $later->id, 'opened_at' => '2026-11-22 00:00:00+00']);
    // Ordem em andamento em um veículo que já voltou a ativo continua aparecendo.
    $progress = MaintenanceOrderModel::factory()->create(['vehicle_id' => $this->bus->id, 'type' => 'preventive', 'status' => 'in_progress', 'opened_at' => '2026-11-20 09:00:00+00']);

    $data = timeline();

    expect(array_column($data['vehicles'], 'plate'))->toBe(['AAA1A11', 'OFI1C11'])
        ->and($data['vehicles'][0]['maintenance_orders'])->toBe([
            ['id' => $progress->id, 'type' => 'preventive', 'status' => 'in_progress', 'opened_at' => '2026-11-20T09:00:00+00:00'],
        ])
        ->and($data['vehicles'][1]['status'])->toBe('maintenance')
        ->and($data['vehicles'][1]['maintenance_orders'])->toBe([
            ['id' => $open->id, 'type' => 'corrective', 'status' => 'open', 'opened_at' => '2026-11-18T14:00:00+00:00'],
        ]);
});

it('puts each trip under its own vehicle and driver', function (): void {
    $otherBus = VehicleModel::factory()->create(['plate' => 'BBB2B22', 'status' => 'active']);
    $bruno = EmployeeModel::factory()->create(['name' => 'Bruno Dias', 'status' => 'active']);

    $first = timelineTrip('2026-11-20 08:00:00', '2026-11-20 12:00:00');
    $second = timelineTrip('2026-11-20 09:00:00', '2026-11-20 13:00:00', vehicle: $otherBus, driver: $bruno);
    $third = timelineTrip('2026-11-21 08:00:00', '2026-11-21 12:00:00', driver: $bruno);

    $data = timeline();

    expect(array_column($data['vehicles'][0]['trips'], 'id'))->toBe([$first->id, $third->id])
        ->and(array_column($data['vehicles'][1]['trips'], 'id'))->toBe([$second->id])
        ->and(array_column($data['drivers'][0]['trips'], 'id'))->toBe([$first->id])
        ->and(array_column($data['drivers'][1]['trips'], 'id'))->toBe([$second->id, $third->id]);
});

it('requires a date in the YYYY-MM-DD format', function (string $query): void {
    $this->getJson("/api/v1/operations/schedule-timeline{$query}")->assertUnprocessable()->assertJsonValidationErrors('date');
})->with(['', '?date=', '?date=20/11/2026', '?date=2026-11-20T08:00:00Z', '?date=2026-13-40']);

it('is open to who can see the operation and closed to the others', function (UserRole $role, int $status): void {
    actingAsRole($role);

    $this->getJson('/api/v1/operations/schedule-timeline?date=2026-11-20')->assertStatus($status);
})->with([
    [UserRole::Dispatcher, 200],
    [UserRole::FleetManager, 200],
    [UserRole::MechanicSupervisor, 403],
]);
