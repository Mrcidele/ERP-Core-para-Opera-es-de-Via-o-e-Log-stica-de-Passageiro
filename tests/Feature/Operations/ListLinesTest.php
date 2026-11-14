<?php

declare(strict_types=1);

use App\Infrastructure\Database\Models\LineModel;

it('lists lines ordered by origin and destination', function (): void {
    LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => 'Ourinhos']);
    LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => 'Londrina']);
    LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => 'Maringá'])->delete();

    $this->getJson('/api/v1/operations/lines')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.destination', 'Londrina')
        ->assertJsonPath('data.1.destination', 'Ourinhos')
        ->assertJsonStructure(['data' => [['id', 'origin', 'destination', 'distance_km', 'estimated_duration_minutes']]]);
});
