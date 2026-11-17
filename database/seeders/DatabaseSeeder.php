<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\FuelLogModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\LineStopModel;
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

        // Linhas com as paradas intermediárias: [cidade, km acumulado, minutos desde a partida].
        foreach ([
            ['Curitiba', 'Ourinhos', 400, 390, [['Ponta Grossa', 115, 105], ['Castro', 160, 150], ['Jaguariaíva', 240, 230], ['Santo Antônio da Platina', 345, 335]]],
            ['Curitiba', 'Londrina', 380, 360, [['Ponta Grossa', 115, 105], ['Ortigueira', 245, 230], ['Apucarana', 325, 305]]],
            ['Curitiba', 'Foz do Iguaçu', 640, 600, [['Guarapuava', 255, 240], ['Laranjeiras do Sul', 365, 345], ['Cascavel', 500, 470], ['Medianeira', 580, 545]]],
            ['Curitiba', 'Florianópolis', 300, 285, [['Joinville', 130, 120], ['Itajaí', 215, 200], ['Balneário Camboriú', 225, 215]]],
            ['Curitiba', 'São Paulo', 410, 390, [['Registro', 215, 200]]],
            ['Curitiba', 'Ponta Grossa', 115, 120, []],
        ] as [$origin, $destination, $distance, $minutes, $stops]) {
            $line = LineModel::factory()->create([
                'origin' => $origin,
                'destination' => $destination,
                'distance_km' => $distance,
                'estimated_duration_minutes' => $minutes,
            ]);

            foreach ($stops as $index => [$city, $km, $elapsed]) {
                LineStopModel::query()->create([
                    'line_id' => $line->id,
                    'sequence' => $index + 1,
                    'city_name' => $city,
                    'cumulative_km' => $km,
                    'estimated_minutes' => $elapsed,
                ]);
            }
        }

        // Abastecimentos das últimas semanas de dez veículos: do mais antigo para o mais recente, o hodômetro
        // termina na quilometragem atual. O último veículo roda pouco por litro, para o alerta de consumo aparecer.
        $recorder = UserModel::query()->orderBy('created_at')->first();

        if ($recorder !== null) {
            foreach ($vehicles->take(10)->values() as $index => $vehicle) {
                $kmPerLiter = $index === 9 ? 1.7 : fake()->randomFloat(2, 2.6, 3.4);
                $odometer = $vehicle->current_mileage;

                foreach ([2, 6, 11, 17, 24] as $daysAgo) {
                    $liters = fake()->numberBetween(180_000, 380_000);

                    FuelLogModel::factory()->create([
                        'vehicle_id' => $vehicle->id,
                        'recorded_by_user_id' => $recorder->id,
                        'odometer_km' => max(0, $odometer),
                        'liters_milliliters' => $liters,
                        // Diesel entre R$ 5,70 e R$ 6,30 o litro.
                        'total_cost_cents' => (int) round($liters / 1000 * fake()->numberBetween(570, 630)),
                        'fueled_at' => now()->subDays($daysAgo)->setTime(fake()->numberBetween(5, 21), fake()->randomElement([0, 15, 30, 45])),
                    ]);

                    // O abastecimento anterior aconteceu "litros × rendimento" quilômetros antes.
                    $odometer -= (int) round($liters / 1000 * $kmPerLiter);
                }
            }
        }

        // Todo veículo em manutenção tem uma ordem aberta; alguns ativos têm histórico de ordens concluídas.
        // Metade com previsão de término nas próximas horas, para a grade de escala mostrar quando o veículo volta.
        foreach ($vehicles->where('status', VehicleStatus::Maintenance)->values() as $index => $vehicle) {
            MaintenanceOrderModel::factory()->create([
                'vehicle_id' => $vehicle->id,
                'mechanic_id' => $mechanics->random()->id,
                'estimated_completion_at' => $index % 2 === 0 ? now()->addHours(fake()->numberBetween(4, 30))->startOfHour() : null,
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
