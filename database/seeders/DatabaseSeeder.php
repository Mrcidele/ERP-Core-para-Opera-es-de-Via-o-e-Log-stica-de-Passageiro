<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\VehicleModel;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $vehicles = VehicleModel::factory(30)->create();

        EmployeeModel::factory(20)->create();
        $mechanics = EmployeeModel::factory(5)->withoutLicense(EmployeeRole::Mechanic)->create();
        EmployeeModel::factory(3)->withoutLicense(EmployeeRole::Admin)->create();

        foreach ([
            ['Curitiba', 'Ourinhos', 400, 390],
            ['Curitiba', 'Londrina', 380, 360],
            ['Curitiba', 'Foz do Iguaçu', 640, 600],
            ['Curitiba', 'Florianópolis', 300, 285],
            ['Curitiba', 'São Paulo', 410, 390],
            ['Curitiba', 'Ponta Grossa', 115, 120],
        ] as [$origin, $destination, $distance, $minutes]) {
            LineModel::factory()->create([
                'origin' => $origin,
                'destination' => $destination,
                'distance_km' => $distance,
                'estimated_duration_minutes' => $minutes,
            ]);
        }

        // Todo veículo em manutenção tem uma ordem aberta; alguns ativos têm histórico de ordens concluídas.
        foreach ($vehicles->where('status', VehicleStatus::Maintenance) as $vehicle) {
            MaintenanceOrderModel::factory()->create([
                'vehicle_id' => $vehicle->id,
                'mechanic_id' => $mechanics->random()->id,
            ]);
        }

        foreach ($vehicles->where('status', VehicleStatus::Active)->take(8) as $vehicle) {
            MaintenanceOrderModel::factory()->completed()->create([
                'vehicle_id' => $vehicle->id,
                'mechanic_id' => $mechanics->random()->id,
            ]);
        }
    }
}
