<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;

beforeEach(fn () => actingAsRole(UserRole::Admin));

function pagedTrip(string $departure, string $status, ?LineModel $line = null, ?VehicleModel $vehicle = null, ?EmployeeModel $driver = null): TripModel
{
    return TripModel::query()->create([
        'line_id' => ($line ?? LineModel::factory()->create())->id,
        'vehicle_id' => ($vehicle ?? VehicleModel::factory()->create())->id,
        'driver_id' => ($driver ?? EmployeeModel::factory()->create())->id,
        'departure_time' => $departure,
        'arrival_time' => date('Y-m-d H:i:s', strtotime($departure) + 6 * 3600),
        'status' => $status,
    ]);
}

it('pages every listing with 15 records by default and reports the totals', function (string $uri, Closure $seed): void {
    $seed();

    $this->getJson($uri)
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2, 'per_page' => 15, 'total' => 17]);

    $this->getJson("{$uri}?page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2);

    $this->getJson("{$uri}?page=9")->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 17);
    $this->getJson("{$uri}?per_page=5&page=4")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 4);
})->with([
    'vehicles' => ['/api/v1/fleet/vehicles', fn () => VehicleModel::factory(17)->create()],
    'employees' => ['/api/v1/crew/employees', fn () => EmployeeModel::factory(17)->create()],
    'trips' => ['/api/v1/operations/trips', function (): void {
        foreach (range(1, 17) as $day) {
            pagedTrip(sprintf('2026-12-%02d 08:00:00', $day), 'scheduled');
        }
    }],
    'maintenance orders' => ['/api/v1/maintenance/orders', function (): void {
        $mechanic = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create();
        VehicleModel::factory(17)->create()->each(fn (VehicleModel $vehicle) => MaintenanceOrderModel::factory()->create(['vehicle_id' => $vehicle->id, 'mechanic_id' => $mechanic->id]));
    }],
]);

it('rejects invalid paging and filter parameters', function (string $uri, string $query, string $field): void {
    $this->getJson("{$uri}?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['/api/v1/fleet/vehicles', 'page=0', 'page'],
    ['/api/v1/fleet/vehicles', 'per_page=101', 'per_page'],
    ['/api/v1/fleet/vehicles', 'status=scheduled', 'status'],
    ['/api/v1/crew/employees', 'per_page=abc', 'per_page'],
    ['/api/v1/crew/employees', 'status=maintenance', 'status'],
    ['/api/v1/operations/trips', 'status=open', 'status'],
    ['/api/v1/maintenance/orders', 'status=scheduled', 'status'],
    ['/api/v1/maintenance/orders', 'search='.str_repeat('a', 101), 'search'],
]);

it('filters vehicles by status and by plate or chassis', function (): void {
    VehicleModel::factory()->create(['plate' => 'ABC1D23', 'chassis' => '9BWZZZ377VT004251', 'status' => 'active']);
    VehicleModel::factory()->create(['plate' => 'XYZ9K88', 'chassis' => '9BWZZZ377VT009999', 'status' => 'maintenance']);
    VehicleModel::factory()->create(['plate' => 'ABD4E56', 'chassis' => '8AWQQQ111VT000001', 'status' => 'inactive']);

    $this->getJson('/api/v1/fleet/vehicles?status=maintenance')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.plate', 'XYZ9K88');
    $this->getJson('/api/v1/fleet/vehicles?search=ab')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/fleet/vehicles?search=abc-1d23')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/fleet/vehicles?search=QQQ')->assertOk()->assertJsonPath('data.0.plate', 'ABD4E56');
    $this->getJson('/api/v1/fleet/vehicles?search=ab&status=inactive')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.plate', 'ABD4E56');
    // % e _ digitados na busca são texto, não curingas.
    $this->getJson('/api/v1/fleet/vehicles?search=%25')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/fleet/vehicles?search=A_C')->assertOk()->assertJsonCount(0, 'data');
});

it('filters employees by status and by name or CPF', function (): void {
    EmployeeModel::factory()->create(['name' => 'Maria Souza', 'cpf' => '11144477735', 'status' => 'active']);
    EmployeeModel::factory()->create(['name' => 'Mário Lima', 'cpf' => '52998224725', 'status' => 'inactive']);
    EmployeeModel::factory()->create(['name' => 'Bruno Dias', 'cpf' => '39053344705', 'status' => 'active']);

    $this->getJson('/api/v1/crew/employees?search=souza')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Maria Souza');
    $this->getJson('/api/v1/crew/employees?search=529.982')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Mário Lima');
    $this->getJson('/api/v1/crew/employees?status=active')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/crew/employees?status=inactive&search=bruno')->assertOk()->assertJsonCount(0, 'data');
});

it('filters trips by status and by line, plate or driver', function (): void {
    $londrina = LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => 'Xanxerê']);
    $bus = VehicleModel::factory()->create(['plate' => 'QQQ1A11']);
    $ana = EmployeeModel::factory()->create(['name' => 'Zuleica Quintão']);

    pagedTrip('2026-12-01 08:00:00', 'scheduled', line: $londrina);
    pagedTrip('2026-12-02 08:00:00', 'completed', vehicle: $bus);
    pagedTrip('2026-12-03 08:00:00', 'canceled', driver: $ana);

    $this->getJson('/api/v1/operations/trips?status=completed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.vehicle.plate', 'QQQ1A11');
    $this->getJson('/api/v1/operations/trips?search=xanxer')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'scheduled');
    $this->getJson('/api/v1/operations/trips?search=qqq1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/operations/trips?search=zuleica')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'canceled');
    $this->getJson('/api/v1/operations/trips?search=zuleica&status=scheduled')->assertOk()->assertJsonCount(0, 'data');
});

it('filters maintenance orders by status and by plate, mechanic or description', function (): void {
    $mechanic = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['name' => 'Zeca Torres']);
    $other = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['name' => 'Rui Prado']);

    MaintenanceOrderModel::factory()->create(['vehicle_id' => VehicleModel::factory()->create(['plate' => 'OFI1C11'])->id, 'mechanic_id' => $mechanic->id, 'description' => 'Troca de pastilhas de freio']);
    MaintenanceOrderModel::factory()->completed()->create(['vehicle_id' => VehicleModel::factory()->create(['plate' => 'OFI2C22'])->id, 'mechanic_id' => $other->id, 'description' => 'Revisão geral']);

    $this->getJson('/api/v1/maintenance/orders?status=completed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.vehicle.plate', 'OFI2C22');
    $this->getJson('/api/v1/maintenance/orders?search=ofi')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/maintenance/orders?search=zeca')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.vehicle.plate', 'OFI1C11');
    $this->getJson('/api/v1/maintenance/orders?search=freio')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/maintenance/orders?search=freio&status=completed')->assertOk()->assertJsonCount(0, 'data');
});
