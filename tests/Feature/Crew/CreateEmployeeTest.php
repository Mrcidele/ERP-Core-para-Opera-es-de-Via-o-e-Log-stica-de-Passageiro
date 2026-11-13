<?php

declare(strict_types=1);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeePayload(array $overrides = []): array
{
    return [
        'name' => 'João da Silva',
        'cpf' => '529.982.247-25',
        'role' => 'driver',
        'status' => 'active',
        'cnh_number' => '12345678901',
        'cnh_category' => 'D',
        'cnh_expiration_date' => now()->addYear()->toDateString(),
        ...$overrides,
    ];
}

it('creates a driver', function (): void {
    $response = $this->postJson('/api/v1/crew/employees', employeePayload());

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'cpf', 'role', 'status', 'cnh_number', 'cnh_category', 'cnh_expiration_date']])
        ->assertJsonPath('data.name', 'João da Silva')
        ->assertJsonPath('data.cpf', '52998224725')
        ->assertJsonPath('data.role', 'driver')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.cnh_category', 'D')
        ->assertJsonPath('data.cnh_expiration_date', now()->addYear()->toDateString());

    $this->assertDatabaseHas('employees', ['cpf' => '52998224725', 'role' => 'driver', 'cnh_number' => '12345678901']);
});

it('creates a mechanic without a license', function (): void {
    $this->postJson('/api/v1/crew/employees', employeePayload([
        'role' => 'mechanic',
        'cnh_number' => null,
        'cnh_category' => null,
        'cnh_expiration_date' => null,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.cnh_number', null)
        ->assertJsonPath('data.cnh_expiration_date', null);
});

it('rejects invalid input', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/crew/employees', employeePayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('employees', 0);
})->with([
    'short name' => [['name' => 'Jo'], 'name'],
    'cpf with wrong check digits' => [['cpf' => '529.982.247-26'], 'cpf'],
    'cpf with repeated digits' => [['cpf' => '111.111.111-11'], 'cpf'],
    'unknown role' => [['role' => 'attendant'], 'role'],
    'unknown status' => [['status' => 'fired'], 'status'],
    'driver without license number' => [['cnh_number' => null], 'cnh_number'],
    'driver without license category' => [['cnh_category' => null], 'cnh_category'],
    'driver without license expiration' => [['cnh_expiration_date' => null], 'cnh_expiration_date'],
    'malformed license number' => [['cnh_number' => '123'], 'cnh_number'],
    'unknown license category' => [['cnh_category' => 'Z'], 'cnh_category'],
    'license expiring today' => [['cnh_expiration_date' => date('Y-m-d')], 'cnh_expiration_date'],
    'expired license' => [['cnh_expiration_date' => date('Y-m-d', strtotime('-1 day'))], 'cnh_expiration_date'],
]);

it('rejects a duplicated CPF', function (): void {
    $this->postJson('/api/v1/crew/employees', employeePayload())->assertCreated();

    $this->postJson('/api/v1/crew/employees', employeePayload(['cnh_number' => '10987654321']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cpf');

    $this->assertDatabaseCount('employees', 1);
});

it('rejects a duplicated license number', function (): void {
    $this->postJson('/api/v1/crew/employees', employeePayload())->assertCreated();

    $this->postJson('/api/v1/crew/employees', employeePayload(['cpf' => '111.444.777-35']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cnh_number');
});

it('allows registering again the CPF of a deleted employee', function (): void {
    $this->postJson('/api/v1/crew/employees', employeePayload())->assertCreated();

    App\Infrastructure\Database\Models\EmployeeModel::query()->firstOrFail()->delete();

    $this->postJson('/api/v1/crew/employees', employeePayload())->assertCreated();

    $this->assertDatabaseCount('employees', 2);
});
