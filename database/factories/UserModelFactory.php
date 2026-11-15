<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<UserModel>
 */
final class UserModelFactory extends Factory
{
    protected $model = UserModel::class;

    private static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Dispatcher,
            'password' => self::$password ??= Hash::make('Password@123'),
        ];
    }

    public function role(UserRole $role): self
    {
        return $this->state(['role' => $role]);
    }
}
