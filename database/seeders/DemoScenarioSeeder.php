<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\MaintenanceType;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\FuelLogModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\UserModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cenário de demonstração comercial: uma frota de 9 caminhões com abastecimentos recentes (dois abaixo da meta de
 * km/L), revisões preventivas vencendo, uma OS aberta e a escala dos próximos dias.
 *
 * Só mexe nos próprios registros, reconhecidos pelas placas, CPFs e chassis fixos abaixo: rodar de novo apaga o
 * histórico deles (abastecimentos, viagens e OS) e monta tudo outra vez a partir da data atual. Não usa factories
 * nem Faker, que não existem na imagem de produção, e não cria nem altera usuários: os abastecimentos ficam em nome
 * da conta de demonstração, que o comando `erp:seed-demo` prepara antes.
 */
final class DemoScenarioSeeder extends Seeder
{
    public const string DEMO_EMAIL = 'demo@frotacarga.com.br';

    /**
     * [placa, chassi, tipo, modelo, carga útil kg, status, km atual, km da próxima revisão, meta própria m/L,
     *  km/L real dos abastecimentos]. Os dois com km/L abaixo da meta disparam o alerta de consumo.
     *
     * @var list<array{string, string, VehicleType, string, int, VehicleStatus, int, int, int|null, float}>
     */
    public const array VEHICLES = [
        ['BEE4R22', '9BVRG40D5PE312401', VehicleType::TractorUnit, 'Volvo FH 540', 32_000, VehicleStatus::Active, 284_310, 284_900, 2_300, 2.48],
        ['RHF2C19', '9BSR6X400N3912202', VehicleType::TractorUnit, 'Scania R450', 32_000, VehicleStatus::Active, 412_875, 420_000, null, 1.68],
        ['AYK7J45', '9BM958074MB118803', VehicleType::TractorUnit, 'Mercedes-Benz Actros 2651', 33_000, VehicleStatus::Active, 198_460, 198_310, null, 2.31],
        ['QTP3D81', '9BWS37BL1PR500904', VehicleType::Bitruck, 'VW Constellation 30.330', 18_500, VehicleStatus::Active, 351_020, 351_900, null, 2.86],
        ['SDF5B30', '9BVVM330XLE100505', VehicleType::Bitruck, 'Volvo VM 330', 18_000, VehicleStatus::Maintenance, 467_300, 470_000, null, 2.74],
        ['RTV8H12', '9BM979277NB210606', VehicleType::Truck, 'Mercedes-Benz Atego 2430', 13_000, VehicleStatus::Active, 226_745, 226_325, null, 3.92],
        ['BCA1E56', '9BWAG45R2NR300707', VehicleType::Toco, 'VW Delivery 13.180', 7_200, VehicleStatus::Active, 143_980, 144_600, null, 5.04],
        ['FRT6G04', '93ZC70C1MP8400808', VehicleType::Vuc, 'Iveco Daily 70C17', 3_800, VehicleStatus::Active, 96_410, 110_000, null, 4.12],
        ['PNG9A77', '9BD250AH2N9500909', VehicleType::Utility, 'Fiat Ducato Cargo', 1_500, VehicleStatus::Active, 71_255, 80_000, null, 9.25],
    ];

    /** Motoristas: [nome, base do CPF (9 dígitos), CNH, categoria, dias até vencer a CNH]. */
    private const array DRIVERS = [
        ['Edson Ribeiro Martins', '904118223', '90411822301', 'E', 840],
        ['Marcos Vinícius Kloster', '904118224', '90411822402', 'E', 610],
        ['Rodrigo Fagundes Lima', '904118225', '90411822503', 'E', 23],
        ['Luciano Pacheco Dalla Costa', '904118226', '90411822604', 'E', 1_120],
        ['Jefferson Alves Bueno', '904118227', '90411822705', 'D', 455],
        ['Sérgio Antunes Mazur', '904118228', '90411822806', 'C', 730],
    ];

    private const array MECHANIC = ['Valdir Prestes Wolff', '904118229'];

    /** Rotas da escala: [origem, destino, km, minutos]. Reaproveita a rota se já existir. */
    private const array LINES = [
        ['Cascavel', 'Paranaguá', 600, 660],
        ['Maringá', 'Paranaguá', 520, 570],
        ['Araucária', 'Ponta Grossa', 105, 115],
        ['São José dos Pinhais', 'Joinville', 125, 140],
        ['Curitiba', 'Paranaguá', 90, 100],
    ];

    /** Litros de cada abastecimento por tipo, do mais recente ao mais antigo, e os dias atrás de cada um. */
    private const array LITERS_BY_TYPE = [
        'cavalo_mecanico' => [512.4, 488.0, 530.6, 465.2, 501.8],
        'bitruck' => [298.5, 312.0, 276.4, 305.9, 290.2],
        'truck' => [214.3, 198.7, 225.0, 207.6, 219.1],
        'toco' => [142.8, 151.2, 136.5, 148.0, 139.9],
        'vuc' => [84.6, 79.2, 88.1, 81.5, 86.0],
        'utilitario' => [58.4, 61.0, 55.7, 60.2, 57.3],
    ];

    private const array FUELED_DAYS_AGO = [1, 4, 8, 13, 19];

    private const array STATIONS = ['Posto Graal Ponta Grossa', 'Posto Rodoanel Curitiba', 'Garagem Central', 'Posto Sete Quedas Cascavel', 'Posto Serra do Mar'];

    public function run(Clock $clock): void
    {
        $now = CarbonImmutable::instance($clock->now())->setTimezone('UTC');

        $user = UserModel::query()->where('email', self::DEMO_EMAIL)->first()
            ?? throw new RuntimeException('Crie antes a conta '.self::DEMO_EMAIL.' (php artisan erp:seed-demo).');

        DB::transaction(function () use ($now, $user): void {
            $vehicles = $this->vehicles();
            $drivers = $this->drivers($now);
            $mechanic = $this->mechanic();
            $vehicleIds = array_map(static fn (VehicleModel $vehicle): string => $vehicle->id, $vehicles);

            // O histórico anterior da demonstração sai inteiro, para a escala não colidir com a de antes.
            TripModel::query()->withTrashed()->whereIn('vehicle_id', $vehicleIds)->forceDelete();
            FuelLogModel::query()->withTrashed()->whereIn('vehicle_id', $vehicleIds)->forceDelete();
            MaintenanceOrderModel::query()->withTrashed()->whereIn('vehicle_id', $vehicleIds)->forceDelete();

            $this->fuelLogs($vehicles, $user->id, $now);
            $this->maintenanceOrders($vehicles, $mechanic, $now);
            $this->trips($vehicles, $drivers, $now);
        });
    }

    /**
     * @return list<VehicleModel>
     */
    private function vehicles(): array
    {
        $vehicles = [];

        foreach (self::VEHICLES as [$plate, $chassis, $type, $model, $payload, $status, $mileage, $nextPreventive, $target]) {
            $vehicle = VehicleModel::query()->withTrashed()->where('plate', $plate)->first() ?? new VehicleModel;

            $vehicle->fill([
                'plate' => $plate,
                'chassis' => $chassis,
                'vehicle_type' => $type,
                'model' => $model,
                'payload_kg' => $payload,
                'status' => $status,
                'current_mileage' => $mileage,
                'next_preventive_maintenance_km' => $nextPreventive,
                'min_meters_per_liter' => $target,
            ]);
            $vehicle->deleted_at = null;
            $vehicle->save();

            $vehicles[] = $vehicle;
        }

        return $vehicles;
    }

    /**
     * @return list<EmployeeModel>
     */
    private function drivers(CarbonImmutable $now): array
    {
        $drivers = [];

        foreach (self::DRIVERS as [$name, $cpfBase, $cnh, $category, $days]) {
            $drivers[] = $this->employee($name, self::cpf($cpfBase), EmployeeRole::Driver, [
                'cnh_number' => $cnh,
                'cnh_category' => $category,
                'cnh_expiration_date' => $now->addDays($days)->toDateString(),
            ]);
        }

        return $drivers;
    }

    private function mechanic(): EmployeeModel
    {
        return $this->employee(self::MECHANIC[0], self::cpf(self::MECHANIC[1]), EmployeeRole::Mechanic, [
            'cnh_number' => null,
            'cnh_category' => null,
            'cnh_expiration_date' => null,
        ]);
    }

    /**
     * @param  array<string, string|null>  $license
     */
    private function employee(string $name, string $cpf, EmployeeRole $role, array $license): EmployeeModel
    {
        $employee = EmployeeModel::query()->withTrashed()->where('cpf', $cpf)->first() ?? new EmployeeModel;

        $employee->fill(['name' => $name, 'cpf' => $cpf, 'role' => $role, 'status' => EmployeeStatus::Active, ...$license]);
        $employee->deleted_at = null;
        $employee->save();

        return $employee;
    }

    /**
     * Abastecimentos do mais recente ao mais antigo: o hodômetro termina na quilometragem atual e cada trecho
     * anterior soma "litros × km/L" do veículo.
     *
     * @param  list<VehicleModel>  $vehicles
     */
    private function fuelLogs(array $vehicles, string $userId, CarbonImmutable $now): void
    {
        foreach ($vehicles as $index => $vehicle) {
            $kmPerLiter = self::VEHICLES[$index][9];
            $odometer = $vehicle->current_mileage;
            // O veículo parado na oficina não abastece desde que entrou.
            $offset = $vehicle->status === VehicleStatus::Maintenance ? 3 : 0;

            foreach (self::LITERS_BY_TYPE[$vehicle->vehicle_type->value] as $position => $liters) {
                $milliliters = (int) round($liters * 1000);
                // Diesel S10 entre R$ 5,89 e R$ 6,19 o litro.
                $pricePerLiterCents = 589 + (($index + $position) * 7) % 31;

                FuelLogModel::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'recorded_by_user_id' => $userId,
                    'odometer_km' => $odometer,
                    'liters_milliliters' => $milliliters,
                    'total_cost_cents' => (int) round($liters * $pricePerLiterCents),
                    'station_name' => self::STATIONS[($index + $position) % count(self::STATIONS)],
                    'fueled_at' => $now->subDays(self::FUELED_DAYS_AGO[$position] + $offset)->setTime(6 + ($index * 2 + $position * 3) % 14, 15 * ($position % 4)),
                ]);

                $odometer -= (int) round($liters * $kmPerLiter);
            }
        }
    }

    /**
     * Uma OS aberta para o bitruck parado e o histórico de preventivas concluídas de três caminhões.
     *
     * @param  list<VehicleModel>  $vehicles
     */
    private function maintenanceOrders(array $vehicles, EmployeeModel $mechanic, CarbonImmutable $now): void
    {
        foreach ($vehicles as $vehicle) {
            if ($vehicle->status === VehicleStatus::Maintenance) {
                MaintenanceOrderModel::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'mechanic_id' => $mechanic->id,
                    'type' => MaintenanceType::Corrective,
                    'status' => MaintenanceStatus::Open,
                    'description' => 'Revisão do freio a ar e troca de lonas do eixo traseiro',
                    'cost_cents' => 0,
                    'opened_at' => $now->subHours(20),
                    'estimated_completion_at' => $now->addHours(6)->startOfHour(),
                    'completed_at' => null,
                ]);
            }
        }

        foreach ([[0, 34, 286_400], [3, 21, 148_900], [6, 47, 92_350]] as [$index, $daysAgo, $cost]) {
            $openedAt = $now->subDays($daysAgo)->setTime(7, 30);

            MaintenanceOrderModel::query()->create([
                'vehicle_id' => $vehicles[$index]->id,
                'mechanic_id' => $mechanic->id,
                'type' => MaintenanceType::Preventive,
                'status' => MaintenanceStatus::Completed,
                'description' => 'Troca de óleo do motor, filtros de diesel e lubrificação do chassi',
                'cost_cents' => $cost,
                'opened_at' => $openedAt,
                'estimated_completion_at' => null,
                'completed_at' => $openedAt->addHours(9),
            ]);
        }
    }

    /**
     * Uma viagem em andamento e a escala das próximas 48 horas, cada uma com motorista próprio para respeitar
     * as 11 horas de descanso. O bitruck da oficina fica fora da escala.
     *
     * @param  list<VehicleModel>  $vehicles
     * @param  list<EmployeeModel>  $drivers
     */
    private function trips(array $vehicles, array $drivers, CarbonImmutable $now): void
    {
        $lines = array_map(fn (array $line): LineModel => $this->line(...$line), self::LINES);
        $start = $now->startOfHour();

        // [veículo, motorista, rota, horas a partir de agora, status]
        foreach ([
            [0, 0, 0, -3, TripStatus::InProgress],
            [1, 1, 1, 2, TripStatus::Scheduled],
            [3, 3, 2, 5, TripStatus::Scheduled],
            [5, 4, 3, 9, TripStatus::Scheduled],
            [2, 5, 4, 22, TripStatus::Scheduled],
            [7, 2, 4, 30, TripStatus::Scheduled],
        ] as [$vehicle, $driver, $line, $hours, $status]) {
            $departure = $start->addHours($hours);

            TripModel::query()->create([
                'line_id' => $lines[$line]->id,
                'vehicle_id' => $vehicles[$vehicle]->id,
                'driver_id' => $drivers[$driver]->id,
                'departure_time' => $departure,
                'arrival_time' => $departure->addMinutes($lines[$line]->estimated_duration_minutes),
                'status' => $status,
            ]);
        }
    }

    private function line(string $origin, string $destination, int $distance, int $minutes): LineModel
    {
        return LineModel::query()->where('origin', $origin)->where('destination', $destination)->first()
            ?? LineModel::query()->create([
                'origin' => $origin,
                'destination' => $destination,
                'distance_km' => $distance,
                'estimated_duration_minutes' => $minutes,
            ]);
    }

    /** CPF com os dígitos verificadores calculados a partir dos 9 primeiros. */
    private static function cpf(string $base): string
    {
        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $base[$i] * ($length + 1 - $i);
            }

            $base .= (string) (($sum * 10) % 11 % 10);
        }

        return $base;
    }
}
