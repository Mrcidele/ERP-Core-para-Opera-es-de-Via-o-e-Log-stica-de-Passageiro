<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\UserModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuários de teste, um por papel, todos com a senha Password@123. Nunca em produção.
        if (! app()->isProduction()) {
            foreach ([
                ['Ana Administradora', 'admin@erp.test', UserRole::Admin],
                ['Diego Despachante', 'despachante@erp.test', UserRole::Dispatcher],
                ['Fernanda Frota', 'frota@erp.test', UserRole::FleetManager],
                ['Otávio Oficina', 'oficina@erp.test', UserRole::MechanicSupervisor],
            ] as [$name, $email, $role]) {
                UserModel::factory()->role($role)->create(['name' => $name, 'email' => $email]);
            }
        }

        $vehicles = VehicleModel::factory(30)->create();

        $drivers = EmployeeModel::factory(20)->create();

        // Três motoristas com a CNH perto do vencimento, para o alerta do painel.
        foreach ([6, 14, 27] as $index => $days) {
            $drivers[$index]->update(['cnh_expiration_date' => now()->addDays($days)->toDateString()]);
        }
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

        // Viagens: uma em andamento e as próximas da escala, cada uma com veículo e motorista próprios
        // (respeitando o descanso de 11 horas) e CNH válida até a chegada.
        $lines = LineModel::query()->orderBy('destination')->get();
        $activeVehicles = $vehicles->where('status', VehicleStatus::Active)->values();
        $longLicense = $drivers->slice(3)->values();

        foreach ([[-2, 'in_progress'], [3, 'scheduled'], [7, 'scheduled'], [20, 'scheduled'], [28, 'scheduled'], [46, 'scheduled'], [70, 'scheduled']] as $index => [$hours, $status]) {
            $line = $lines[$index % $lines->count()];
            $departure = now()->startOfHour()->addHours($hours);

            TripModel::query()->create([
                'line_id' => $line->id,
                'vehicle_id' => $activeVehicles[$index]->id,
                'driver_id' => $longLicense[$index]->id,
                'departure_time' => $departure,
                'arrival_time' => $departure->copy()->addMinutes($line->estimated_duration_minutes),
                'status' => $status,
            ]);
        }
    }
}
