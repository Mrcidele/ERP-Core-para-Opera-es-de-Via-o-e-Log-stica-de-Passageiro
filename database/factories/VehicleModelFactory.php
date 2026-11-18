<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
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
        $type = fake()->randomElement(VehicleType::cases());

        return [
            'plate' => fake()->unique()->regexify('[A-Z]{3}[0-9][A-J][0-9]{2}'),
            'chassis' => fake()->unique()->regexify('9B[A-HJ-NPR-Z0-9]{15}'),
            'vehicle_type' => $type,
            'payload_kg' => self::typicalPayloadKg($type),
            'status' => fake()->randomElement([VehicleStatus::Active, VehicleStatus::Active, VehicleStatus::Active, VehicleStatus::Maintenance, VehicleStatus::Inactive]),
            'current_mileage' => $mileage = fake()->numberBetween(5_000, 900_000),
            // Revisão a cada 20 mil km; cerca de um em cada seis veículos já passou do ponto.
            'next_preventive_maintenance_km' => $mileage + fake()->randomElement([-fake()->numberBetween(200, 6_000), 800, 4_000, 9_000, 14_000, 19_000]),
        ];
    }

    /** Carga útil típica de cada tipo de caminhão, em quilos. */
    public static function typicalPayloadKg(VehicleType $type): int
    {
        return match ($type) {
            VehicleType::Utility => 1_500,
            VehicleType::Vuc => 3_500,
            VehicleType::Toco => 6_000,
            VehicleType::Truck => 12_000,
            VehicleType::Bitruck => 18_000,
            VehicleType::TractorUnit => 32_000,
        };
    }
}
