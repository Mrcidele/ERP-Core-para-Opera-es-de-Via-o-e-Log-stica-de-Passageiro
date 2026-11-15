<?php

declare(strict_types=1);

use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\LineStopModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\QueryException;

const UNKNOWN_LINE = '0193a5b0-7c1e-7000-8000-000000000000';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function linePayload(array $overrides = []): array
{
    return [
        'origin' => 'Curitiba',
        'destination' => 'Ourinhos',
        'distance_km' => 400,
        'estimated_duration_minutes' => 390,
        'stops' => [
            ['city_name' => 'Ponta Grossa', 'cumulative_km' => 115, 'estimated_minutes' => 105],
            ['city_name' => 'Castro', 'cumulative_km' => 160, 'estimated_minutes' => 150],
        ],
        ...$overrides,
    ];
}

function tripOnLine(LineModel $line, string $status): TripModel
{
    return TripModel::query()->create([
        'line_id' => $line->id,
        'vehicle_id' => VehicleModel::factory()->create(['status' => 'active'])->id,
        'driver_id' => EmployeeModel::factory()->create()->id,
        'departure_time' => '2026-11-20 11:00:00',
        'arrival_time' => '2026-11-20 17:30:00',
        'status' => $status,
    ]);
}

it('creates a line with its stops in order', function (): void {
    $response = $this->postJson('/api/v1/operations/lines', linePayload())
        ->assertCreated()
        ->assertJsonPath('data.origin', 'Curitiba')
        ->assertJsonPath('data.destination', 'Ourinhos')
        ->assertJsonPath('data.distance_km', 400)
        ->assertJsonPath('data.stops', [
            ['sequence' => 1, 'city_name' => 'Ponta Grossa', 'cumulative_km' => 115, 'estimated_minutes' => 105],
            ['sequence' => 2, 'city_name' => 'Castro', 'cumulative_km' => 160, 'estimated_minutes' => 150],
        ]);

    $id = $response->json('data.id');

    $this->assertDatabaseHas('lines', ['id' => $id, 'destination' => 'Ourinhos']);
    $this->assertDatabaseHas('line_stops', ['line_id' => $id, 'sequence' => 2, 'city_name' => 'Castro']);
    $this->getJson('/api/v1/operations/lines')->assertOk()->assertJsonPath('data.0.stops.1.city_name', 'Castro');

    $log = AuditLogModel::query()->sole();

    expect([$log->action, $log->auditable_type, $log->auditable_id, $log->old_values])->toBe(['line.created', 'line', $id, null])
        ->and($log->new_values['stops'])->toBe('Ponta Grossa (km 115, 105 min) → Castro (km 160, 150 min)');
});

it('creates a line without stops', function (): void {
    $this->postJson('/api/v1/operations/lines', linePayload(['stops' => []]))->assertCreated()->assertJsonPath('data.stops', []);
    $this->postJson('/api/v1/operations/lines', collect(linePayload(['destination' => 'Londrina']))->except('stops')->all())->assertCreated();

    $this->assertDatabaseCount('line_stops', 0);
});

it('rejects invalid input', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/operations/lines', linePayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('lines', 0);
    $this->assertDatabaseCount('line_stops', 0);
    $this->assertDatabaseCount('audit_logs', 0);
})->with([
    'sem origem' => [['origin' => ''], 'origin'],
    'sem destino' => [['destination' => ''], 'destination'],
    'destino igual à origem' => [['destination' => 'CURITIBA'], 'destination'],
    'distância zero' => [['distance_km' => 0], 'distance_km'],
    'duração zero' => [['estimated_duration_minutes' => 0], 'estimated_duration_minutes'],
    'paradas não é lista' => [['stops' => 'Castro'], 'stops'],
    'parada sem cidade' => [['stops' => [['city_name' => '', 'cumulative_km' => 100, 'estimated_minutes' => 90]]], 'stops.0.city_name'],
    'parada com km negativo' => [['stops' => [['city_name' => 'Castro', 'cumulative_km' => -1, 'estimated_minutes' => 90]]], 'stops.0.cumulative_km'],
    'parada sem tempo' => [['stops' => [['city_name' => 'Castro', 'cumulative_km' => 100]]], 'stops.0.estimated_minutes'],
    'paradas fora de ordem' => [['stops' => [
        ['city_name' => 'Castro', 'cumulative_km' => 160, 'estimated_minutes' => 150],
        ['city_name' => 'Ponta Grossa', 'cumulative_km' => 115, 'estimated_minutes' => 105],
    ]], 'stops'],
    'parada depois do destino' => [['stops' => [['city_name' => 'Assis', 'cumulative_km' => 450, 'estimated_minutes' => 300]]], 'stops'],
]);

it('saves nothing when a stop is refused by the database', function (): void {
    // Falha na segunda parada, dentro da transação: a linha e a primeira parada não podem ficar gravadas.
    LineStopModel::creating(function (LineStopModel $stop): void {
        if ($stop->sequence === 2) {
            $stop->sequence = 0;
        }
    });

    try {
        expect(fn () => $this->withoutExceptionHandling()->postJson('/api/v1/operations/lines', linePayload()))->toThrow(QueryException::class);
    } finally {
        LineStopModel::flushEventListeners();
    }

    $this->assertDatabaseCount('lines', 0);
    $this->assertDatabaseCount('line_stops', 0);
});

it('updates a line and replaces its stops', function (): void {
    $id = $this->postJson('/api/v1/operations/lines', linePayload())->json('data.id');

    $this->putJson("/api/v1/operations/lines/{$id}", linePayload([
        'destination' => 'Jacarezinho',
        'distance_km' => 380,
        'stops' => [
            ['city_name' => 'Castro', 'cumulative_km' => 160, 'estimated_minutes' => 150],
            ['city_name' => 'Jaguariaíva', 'cumulative_km' => 240, 'estimated_minutes' => 230],
            ['city_name' => 'Santo Antônio da Platina', 'cumulative_km' => 345, 'estimated_minutes' => 335],
        ],
    ]))
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.destination', 'Jacarezinho')
        ->assertJsonPath('data.stops.0', ['sequence' => 1, 'city_name' => 'Castro', 'cumulative_km' => 160, 'estimated_minutes' => 150])
        ->assertJsonCount(3, 'data.stops');

    $this->assertDatabaseCount('line_stops', 3);
    $this->assertDatabaseMissing('line_stops', ['city_name' => 'Ponta Grossa']);

    $log = AuditLogModel::query()->where('action', 'line.updated')->sole();

    expect($log->old_values['destination'])->toBe('Ourinhos')
        ->and($log->new_values['destination'])->toBe('Jacarezinho')
        ->and($log->new_values['distance_km'])->toBe(380);
});

it('keeps the line as it was when the update is refused', function (): void {
    $id = $this->postJson('/api/v1/operations/lines', linePayload())->json('data.id');

    $this->putJson("/api/v1/operations/lines/{$id}", linePayload([
        'destination' => 'Jacarezinho',
        'stops' => [['city_name' => 'Assis', 'cumulative_km' => 999, 'estimated_minutes' => 300]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('stops');

    $this->assertDatabaseHas('lines', ['id' => $id, 'destination' => 'Ourinhos']);
    $this->assertDatabaseCount('line_stops', 2);
});

it('deletes a line that only has finished trips', function (string $status): void {
    $line = LineModel::factory()->create();
    LineStopModel::query()->create(['line_id' => $line->id, 'sequence' => 1, 'city_name' => 'Castro', 'cumulative_km' => 10, 'estimated_minutes' => 10]);
    tripOnLine($line, $status);

    $this->deleteJson("/api/v1/operations/lines/{$line->id}")->assertNoContent();

    $this->assertSoftDeleted('lines', ['id' => $line->id]);
    $this->getJson('/api/v1/operations/lines')->assertOk()->assertJsonCount(0, 'data');
    // A viagem antiga continua mostrando a linha.
    $this->getJson('/api/v1/operations/trips')->assertOk()->assertJsonPath('data.0.line.destination', $line->destination);

    expect(AuditLogModel::query()->sole()->action)->toBe('line.deleted');
})->with(['completed', 'canceled']);

it('refuses to delete a line with a scheduled or running trip', function (string $status): void {
    $line = LineModel::factory()->create();
    tripOnLine($line, $status);

    $this->deleteJson("/api/v1/operations/lines/{$line->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['line' => 'Esta linha tem viagens agendadas ou em andamento. Conclua ou cancele as viagens antes de excluí-la.']);

    $this->assertNotSoftDeleted('lines', ['id' => $line->id]);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['scheduled', 'in_progress']);

it('returns 404 for an unknown or deleted line', function (string $method, string $id): void {
    $deleted = LineModel::factory()->create();
    $deleted->delete();

    $this->json($method, '/api/v1/operations/lines/'.($id === 'deleted' ? $deleted->id : $id), linePayload())->assertNotFound();
})->with([
    ['PUT', UNKNOWN_LINE], ['PUT', 'not-a-uuid'], ['PUT', 'deleted'],
    ['DELETE', UNKNOWN_LINE], ['DELETE', 'not-a-uuid'], ['DELETE', 'deleted'],
]);

it('refuses at the database a stop with invalid numbers or a repeated sequence', function (array $stop): void {
    $line = LineModel::factory()->create();
    LineStopModel::query()->create(['line_id' => $line->id, 'sequence' => 1, 'city_name' => 'Castro', 'cumulative_km' => 10, 'estimated_minutes' => 10]);

    expect(fn () => LineStopModel::query()->create(['line_id' => $line->id, 'city_name' => 'Tibagi', 'sequence' => 2, 'cumulative_km' => 20, 'estimated_minutes' => 20, ...$stop]))
        ->toThrow(QueryException::class);
})->with([
    'sequência zero' => [['sequence' => 0]],
    'sequência repetida' => [['sequence' => 1]],
    'km negativo' => [['cumulative_km' => -1]],
    'minutos negativos' => [['estimated_minutes' => -1]],
]);
