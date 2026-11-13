<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Database\Models\VehicleModel;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        VehicleModel::factory(30)->create();
    }
}
