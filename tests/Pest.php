<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

// Os testes dos módulos rodam como administrador; autenticação e permissões têm testes próprios em Feature/Security.
pest()->beforeEach(fn () => actingAsRole(UserRole::Admin))
    ->in('Feature/Fleet', 'Feature/Crew', 'Feature/Operations', 'Feature/Maintenance');

function actingAsRole(UserRole $role): UserModel
{
    $user = UserModel::factory()->role($role)->create();

    Sanctum::actingAs($user);

    return $user;
}
