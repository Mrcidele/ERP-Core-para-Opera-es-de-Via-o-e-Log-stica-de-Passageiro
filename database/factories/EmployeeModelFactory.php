<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Infrastructure\Database\Models\EmployeeModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeModel>
 */
final class EmployeeModelFactory extends Factory
{
    protected $model = EmployeeModel::class;

    /**
     * Por padrão, um motorista ativo com CNH válida.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'cpf' => self::cpf(),
            'role' => EmployeeRole::Driver,
            'status' => EmployeeStatus::Active,
            'cnh_number' => fake()->unique()->numerify('###########'),
            'cnh_category' => fake()->randomElement(['D', 'E']),
            'cnh_expiration_date' => fake()->dateTimeBetween('+6 months', '+5 years')->format('Y-m-d'),
        ];
    }

    public function withoutLicense(EmployeeRole $role): self
    {
        return $this->state([
            'role' => $role,
            'cnh_number' => null,
            'cnh_category' => null,
            'cnh_expiration_date' => null,
        ]);
    }

    /** CPF único com dígitos verificadores corretos. */
    private static function cpf(): string
    {
        $base = fake()->unique()->numerify('#########');

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
