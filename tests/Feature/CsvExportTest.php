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
    $this->travelTo('2026-11-15 12:00:00');
    actingAsRole(UserRole::Admin);
});

/**
 * @return list<list<string|null>>
 */
function csvRows(string $content): array
{
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");

    return array_map(
        fn (string $line): array => str_getcsv($line, ';', '"', ''),
        explode("\n", trim(substr($content, 3))),
    );
}

function exportedTrip(string $departureUtc, string $status, string $destination, string $plate, string $driver): void
{
    TripModel::query()->create([
        'line_id' => LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => $destination, 'distance_km' => 400])->id,
        'vehicle_id' => VehicleModel::factory()->create(['plate' => $plate])->id,
        'driver_id' => EmployeeModel::factory()->create(['name' => $driver])->id,
        'departure_time' => $departureUtc,
        'arrival_time' => date('Y-m-d H:i:s', strtotime($departureUtc) + 6 * 3600 + 1800),
        'status' => $status,
    ]);
}

it('downloads the trip manifest as CSV, with times in the operation time zone', function (): void {
    exportedTrip('2026-11-20 11:00:00', 'scheduled', 'Ourinhos', 'ABC1D23', 'Ana Lima');
    exportedTrip('2026-11-21 02:30:00', 'completed', 'Londrina', 'XYZ9K88', 'Bruno; Dias');

    $response = $this->get('/api/v1/operations/trips/export')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename=manifesto-de-viagens-2026-11-15.csv');

    expect(csvRows($response->streamedContent()))->toBe([
        ['Rota', 'Distância (km)', 'Placa', 'Motorista', 'Partida', 'Chegada', 'Status'],
        ['Curitiba → Ourinhos', '400', 'ABC1D23', 'Ana Lima', '20/11/2026 08:00', '20/11/2026 14:30', 'Agendada'],
        // 02:30 UTC ainda é a noite anterior em Brasília.
        ['Curitiba → Londrina', '400', 'XYZ9K88', 'Bruno; Dias', '20/11/2026 23:30', '21/11/2026 06:00', 'Concluída'],
    ]);
});

it('exports every trip that matches the filters, not just one page', function (): void {
    foreach (range(1, 20) as $day) {
        exportedTrip(sprintf('2026-12-%02d 11:00:00', $day), $day <= 18 ? 'completed' : 'canceled', "Cidade {$day}", sprintf('TRK%dA%02d', $day % 10, $day), "Motorista {$day}");
    }

    $all = csvRows($this->get('/api/v1/operations/trips/export?per_page=5&page=3')->assertOk()->streamedContent());
    $completed = csvRows($this->get('/api/v1/operations/trips/export?status=completed')->assertOk()->streamedContent());
    $one = csvRows($this->get('/api/v1/operations/trips/export?search=Cidade 7')->assertOk()->streamedContent());

    expect($all)->toHaveCount(21)
        ->and($completed)->toHaveCount(19)
        ->and(array_unique(array_column(array_slice($completed, 1), 6)))->toBe(['Concluída'])
        ->and(array_column(array_slice($one, 1), 0))->toBe(['Curitiba → Cidade 7']);
});

it('downloads the maintenance report as CSV with the cost in reais', function (): void {
    $mechanic = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['name' => 'Zeca Torres']);

    MaintenanceOrderModel::factory()->create([
        'vehicle_id' => VehicleModel::factory()->create(['plate' => 'OFI1C11'])->id,
        'mechanic_id' => $mechanic->id,
        'type' => 'corrective',
        'status' => 'open',
        'description' => '=HYPERLINK("http://x")',
        'cost_cents' => 0,
        'opened_at' => '2026-11-14 13:00:00',
        'completed_at' => null,
    ]);
    MaintenanceOrderModel::factory()->create([
        'vehicle_id' => VehicleModel::factory()->create(['plate' => 'OFI2C22'])->id,
        'mechanic_id' => $mechanic->id,
        'type' => 'preventive',
        'status' => 'completed',
        'description' => 'Revisão dos 100 mil km',
        'cost_cents' => 1_234_590,
        'opened_at' => '2026-11-10 13:00:00',
        'completed_at' => '2026-11-12 20:15:00',
    ]);

    $response = $this->get('/api/v1/maintenance/orders/export')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename=ordens-de-servico-2026-11-15.csv');

    expect(csvRows($response->streamedContent()))->toBe([
        ['Placa', 'Mecânico', 'Tipo', 'Descrição', 'Aberta em', 'Concluída em', 'Custo (R$)', 'Status'],
        // Texto que a planilha leria como fórmula sai com apóstrofo na frente.
        ['OFI1C11', 'Zeca Torres', 'Corretiva', '\'=HYPERLINK("http://x")', '14/11/2026 10:00', '', '0,00', 'Aberta'],
        ['OFI2C22', 'Zeca Torres', 'Preventiva', 'Revisão dos 100 mil km', '10/11/2026 10:00', '12/11/2026 17:15', '12345,90', 'Concluída'],
    ]);

    expect(csvRows($this->get('/api/v1/maintenance/orders/export?status=completed')->streamedContent()))->toHaveCount(2);
});

it('exports only the header when nothing matches', function (): void {
    expect(csvRows($this->get('/api/v1/operations/trips/export')->assertOk()->streamedContent()))->toHaveCount(1)
        ->and(csvRows($this->get('/api/v1/maintenance/orders/export')->assertOk()->streamedContent()))->toHaveCount(1);
});

it('restricts each export to who can see the module', function (UserRole $role, int $trips, int $orders): void {
    actingAsRole($role);

    $this->get('/api/v1/operations/trips/export', ['Accept' => 'application/json'])->assertStatus($trips);
    $this->get('/api/v1/maintenance/orders/export', ['Accept' => 'application/json'])->assertStatus($orders);
})->with([
    [UserRole::Dispatcher, 200, 403],
    [UserRole::MechanicSupervisor, 403, 200],
    [UserRole::FleetManager, 200, 200],
]);

it('rejects an invalid export filter', function (): void {
    $this->getJson('/api/v1/operations/trips/export?status=open')->assertUnprocessable()->assertJsonValidationErrors('status');
});
