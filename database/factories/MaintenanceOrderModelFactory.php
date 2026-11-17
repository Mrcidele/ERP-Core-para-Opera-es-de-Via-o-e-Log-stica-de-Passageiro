<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\MaintenanceType;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceOrderModel>
 */
final class MaintenanceOrderModelFactory extends Factory
{
    protected $model = MaintenanceOrderModel::class;

    /**
     * Por padrão, uma ordem aberta. O veículo criado junto já nasce em manutenção.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => VehicleModel::factory()->state(['status' => 'maintenance']),
            'mechanic_id' => EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic),
            'type' => fake()->randomElement(MaintenanceType::cases()),
            'status' => MaintenanceStatus::Open,
            'description' => fake()->randomElement([
                'Troca de óleo e filtros',
                'Revisão dos freios',
                'Troca de pneus dianteiros',
                'Reparo no ar-condicionado',
                'Alinhamento e balanceamento',
                'Substituição da correia do alternador',
            ]),
            'cost_cents' => 0,
            'opened_at' => fake()->dateTimeBetween('-10 days', '-1 hour'),
            'completed_at' => null,
            'estimated_completion_at' => null,
        ];
    }

    /** Ordem aberta com previsão de término a $hours horas da abertura. */
    public function estimatedIn(int $hours): self
    {
        return $this->state(function (array $attributes) use ($hours): array {
            $openedAt = CarbonImmutable::parse($attributes['opened_at'] instanceof \DateTimeInterface ? $attributes['opened_at']->format(DATE_ATOM) : (string) $attributes['opened_at']);

            return ['estimated_completion_at' => $openedAt->addHours($hours)];
        });
    }

    public function completed(): self
    {
        return $this->state(function (): array {
            $openedAt = fake()->dateTimeBetween('-60 days', '-5 days');

            return [
                'status' => MaintenanceStatus::Completed,
                'cost_cents' => fake()->numberBetween(15_000, 900_000),
                'opened_at' => $openedAt,
                'completed_at' => (clone $openedAt)->modify('+'.fake()->numberBetween(2, 72).' hours'),
            ];
        });
    }
}
