<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Database\Models\LineModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineModel>
 */
final class LineModelFactory extends Factory
{
    protected $model = LineModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $distance = fake()->numberBetween(80, 900);

        return [
            'origin' => 'Curitiba',
            'destination' => fake()->unique()->city(),
            'distance_km' => $distance,
            // Caminhão carregado a 55 km/h de média, arredondado para múltiplos de 5 minutos.
            'estimated_duration_minutes' => (int) (round($distance / 55 * 60 / 5) * 5),
        ];
    }
}
