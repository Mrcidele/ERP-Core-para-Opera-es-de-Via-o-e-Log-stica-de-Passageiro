<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fleet\Enums\VehicleClass;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleModel>
 */
final class VehicleModelFactory extends Factory
{
    protected $model = VehicleModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate' => fake()->unique()->regexify('[A-Z]{3}[0-9][A-J][0-9]{2}'),
            'chassis' => fake()->unique()->regexify('9B[A-HJ-NPR-Z0-9]{15}'),
            'capacity' => fake()->randomElement([28, 42, 44, 46, 50]),
            'class_type' => fake()->randomElement(VehicleClass::cases()),
            'status' => fake()->randomElement([VehicleStatus::Active, VehicleStatus::Active, VehicleStatus::Active, VehicleStatus::Maintenance, VehicleStatus::Inactive]),
            'current_mileage' => $mileage = fake()->numberBetween(5_000, 900_000),
            // Revisão a cada 20 mil km; cerca de um em cada seis veículos já passou do ponto.
            'next_preventive_maintenance_km' => $mileage + fake()->randomElement([-fake()->numberBetween(200, 6_000), 800, 4_000, 9_000, 14_000, 19_000]),
        ];
    }
}
