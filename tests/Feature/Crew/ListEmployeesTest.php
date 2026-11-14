<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\EmployeeRole;
use App\Infrastructure\Database\Models\EmployeeModel;

it('lists employees ordered by name', function (): void {
    EmployeeModel::factory()->create(['name' => 'Maria Souza']);
    EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['name' => 'Ana Lima']);
    EmployeeModel::factory()->create(['name' => 'Bruno Dias'])->delete();

    $this->getJson('/api/v1/crew/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Ana Lima')
        ->assertJsonPath('data.0.role', 'mechanic')
        ->assertJsonPath('data.0.cnh_number', null)
        ->assertJsonPath('data.1.name', 'Maria Souza')
        ->assertJsonPath('data.1.role', 'driver')
        ->assertJsonStructure(['data' => [['id', 'name', 'cpf', 'role', 'status', 'cnh_number', 'cnh_category', 'cnh_expiration_date']]]);
});
