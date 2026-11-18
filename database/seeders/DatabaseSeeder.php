<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\FuelLogModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\LineStopModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\UserModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Database\Factories\VehicleModelFactory;
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

        // Frota de uma transportadora do Paraná: cavalos para carga fechada (lotação), bitrucks e trucks na distribuição
        // regional e VUCs/utilitários na entrega urbana. [tipo, modelo, status]
        $fleet = [
            [VehicleType::TractorUnit, 'Volvo FH 540', VehicleStatus::Active],
            [VehicleType::TractorUnit, 'Volvo FH 540', VehicleStatus::Active],
            [VehicleType::TractorUnit, 'Scania R450', VehicleStatus::Active],
            [VehicleType::TractorUnit, 'Scania R450', VehicleStatus::Active],
            [VehicleType::TractorUnit, 'Mercedes-Benz Actros 2651', VehicleStatus::Active],
            [VehicleType::TractorUnit, 'DAF XF 530', VehicleStatus::Maintenance],
            [VehicleType::TractorUnit, 'Volvo FH 460', VehicleStatus::Active],
            [VehicleType::TractorUnit, 'Scania R450', VehicleStatus::Inactive],
            [VehicleType::Bitruck, 'Volvo VM 330', VehicleStatus::Active],
            [VehicleType::Bitruck, 'Mercedes-Benz Atego 3030', VehicleStatus::Active],
            [VehicleType::Bitruck, 'VW Constellation 30.330', VehicleStatus::Active],
            [VehicleType::Bitruck, 'Scania P 320', VehicleStatus::Maintenance],
            [VehicleType::Truck, 'VW Constellation 24.280', VehicleStatus::Active],
            [VehicleType::Truck, 'Mercedes-Benz Atego 2430', VehicleStatus::Active],
            [VehicleType::Truck, 'Volvo VM 270', VehicleStatus::Active],
            [VehicleType::Truck, 'Iveco Tector 240E28', VehicleStatus::Active],
            [VehicleType::Toco, 'VW Delivery 13.180', VehicleStatus::Active],
            [VehicleType::Toco, 'Mercedes-Benz Accelo 1316', VehicleStatus::Active],
            [VehicleType::Toco, 'Ford Cargo 1119', VehicleStatus::Maintenance],
            [VehicleType::Vuc, 'VW Delivery 9.170', VehicleStatus::Active],
            [VehicleType::Vuc, 'VW Delivery 11.180', VehicleStatus::Active],
            [VehicleType::Vuc, 'Mercedes-Benz Accelo 815', VehicleStatus::Active],
            [VehicleType::Vuc, 'Iveco Daily 70C17', VehicleStatus::Active],
            [VehicleType::Utility, 'Renault Master Furgão', VehicleStatus::Active],
            [VehicleType::Utility, 'Fiat Ducato Cargo', VehicleStatus::Active],
        ];

        $vehicles = collect($fleet)->map(fn (array $vehicle) => VehicleModel::factory()->create([
            'vehicle_type' => $vehicle[0],
            'model' => $vehicle[1],
            'status' => $vehicle[2],
            'payload_kg' => VehicleModelFactory::typicalPayloadKg($vehicle[0]),
        ]));

        // Um cavalo novo com meta própria mais exigente que o padrão do tipo (2,0 km/L).
        $vehicles[0]->update(['min_meters_per_liter' => 2_300]);

        $drivers = EmployeeModel::factory(20)->create();

        // Três motoristas com a CNH perto do vencimento, para o alerta do painel.
        foreach ([6, 14, 27] as $index => $days) {
            $drivers[$index]->update(['cnh_expiration_date' => now()->addDays($days)->toDateString()]);
        }
        $mechanics = EmployeeModel::factory(5)->withoutLicense(EmployeeRole::Mechanic)->create();
        EmployeeModel::factory(3)->withoutLicense(EmployeeRole::Admin)->create();

        // Rotas de carga com os pontos de parada (coleta, entrega ou passagem): [cidade, km acumulado, minutos desde a partida].
        foreach ([
            ['Cascavel', 'Paranaguá', 600, 660, [['Guarapuava', 245, 270], ['Ponta Grossa', 440, 480], ['Curitiba', 510, 560]]],
            ['Maringá', 'Paranaguá', 520, 570, [['Apucarana', 60, 65], ['Ponta Grossa', 360, 395], ['São José dos Pinhais', 440, 485]]],
            ['Araucária', 'Londrina', 390, 430, [['Ponta Grossa', 120, 135], ['Ortigueira', 250, 275]]],
            ['São José dos Pinhais', 'Joinville', 125, 140, [['Garuva', 85, 95]]],
            ['Curitiba', 'Porto Alegre', 710, 780, [['Joinville', 130, 145], ['Itajaí', 215, 235], ['Florianópolis', 300, 330]]],
            ['Curitiba', 'Guarulhos', 420, 465, [['Registro', 215, 240]]],
            ['Araucária', 'Ponta Grossa', 105, 115, []],
            ['Curitiba', 'Paranaguá', 90, 100, []],
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

        // Abastecimentos das últimas semanas dos veículos ativos: do mais antigo para o mais recente, o hodômetro
        // termina na quilometragem atual. Rendimento e volume de diesel conforme o tipo; dois veículos rodam
        // abaixo da meta, para o alerta de consumo aparecer (um cavalo com 1,7 km/L e um VUC com 4,1 km/L).
        $recorder = UserModel::query()->orderBy('created_at')->first();
        // [km/L mínimo, km/L máximo, litros mínimos, litros máximos] por tipo.
        $consumption = [
            VehicleType::TractorUnit->value => [2.0, 2.6, 380, 620],
            VehicleType::Bitruck->value => [2.5, 3.1, 220, 360],
            VehicleType::Truck->value => [3.5, 4.3, 160, 260],
            VehicleType::Toco->value => [4.5, 5.6, 110, 180],
            VehicleType::Vuc->value => [5.0, 6.8, 60, 110],
            VehicleType::Utility->value => [8.0, 10.5, 45, 75],
        ];

        if ($recorder !== null) {
            $fueled = $vehicles->where('status', VehicleStatus::Active)->values();

            foreach ($fueled as $index => $vehicle) {
                [$minKml, $maxKml, $minLiters, $maxLiters] = $consumption[$vehicle->vehicle_type->value];
                $kmPerLiter = match (true) {
                    $index === 0 => fake()->randomFloat(2, 2.4, 2.6),
                    $index === 2 => 1.7,
                    $vehicle->model === 'Iveco Daily 70C17' => 4.1,
                    default => fake()->randomFloat(2, $minKml, $maxKml),
                };
                $odometer = $vehicle->current_mileage;

                foreach ([2, 6, 11, 17, 24] as $daysAgo) {
                    $liters = fake()->numberBetween($minLiters * 1000, $maxLiters * 1000);

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
