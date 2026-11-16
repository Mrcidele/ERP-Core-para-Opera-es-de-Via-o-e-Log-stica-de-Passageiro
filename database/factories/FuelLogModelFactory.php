<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Database\Models\FuelLogModel;
use App\Infrastructure\Database\Models\UserModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuelLogModel>
 */
final class FuelLogModelFactory extends Factory
{
    protected $model = FuelLogModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $liters = fake()->numberBetween(120_000, 420_000);

        return [
            'vehicle_id' => VehicleModel::factory(),
            'recorded_by_user_id' => UserModel::factory(),
            'odometer_km' => fake()->numberBetween(10_000, 800_000),
            'liters_milliliters' => $liters,
            // Diesel entre R$ 5,60 e R$ 6,40 o litro.
            'total_cost_cents' => (int) round($liters / 1000 * fake()->numberBetween(560, 640)),
            'station_name' => fake()->randomElement(['Posto Graal Ourinhos', 'Posto Rodoanel', 'Garagem Central', 'Posto Sete Quedas', 'Posto Serra Azul']),
            'fueled_at' => fake()->dateTimeBetween('-20 days', '-1 hour'),
        ];
    }
}
