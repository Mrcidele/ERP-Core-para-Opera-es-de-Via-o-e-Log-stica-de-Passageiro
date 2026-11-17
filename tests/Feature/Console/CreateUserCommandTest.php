<?php

declare(strict_types=1);

use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Support\Facades\Hash;

const STRONG_PASSWORD = 'Viacao-Segura-2026';

it('creates an administrator from the options and lets them log in', function (): void {
    $this->artisan('erp:create-admin', ['--name' => 'Caio Marcidele', '--email' => 'Caio@Viacao.com', '--password' => STRONG_PASSWORD])
        ->expectsOutput('Usuário criado: Caio Marcidele <caio@viacao.com>, papel Administração.')
        ->assertSuccessful();

    $user = UserModel::query()->where('email', 'caio@viacao.com')->sole();

    expect($user->role->value)->toBe('admin')
        ->and(Hash::check(STRONG_PASSWORD, $user->password))->toBeTrue();

    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/auth/login', ['email' => 'caio@viacao.com', 'password' => STRONG_PASSWORD])
        ->assertOk()
        ->assertJsonPath('data.user.role', 'admin');
});

it('creates a user with another role under its full name', function (): void {
    $this->artisan('erp:create-user', ['--name' => 'Diego Despacho', '--email' => 'diego@viacao.com', '--password' => STRONG_PASSWORD, '--role' => 'dispatcher'])
        ->expectsOutput('Usuário criado: Diego Despacho <diego@viacao.com>, papel Despacho.')
        ->assertSuccessful();

    expect(UserModel::query()->where('email', 'diego@viacao.com')->sole()->role->value)->toBe('dispatcher');
});

it('asks for what was not given, with the password hidden and typed twice', function (): void {
    $this->artisan('erp:create-admin')
        ->expectsQuestion('Nome', 'Caio Marcidele')
        ->expectsQuestion('E-mail', 'caio@viacao.com')
        ->expectsQuestion('Senha (mínimo de 12 caracteres, com maiúsculas, minúsculas e números)', STRONG_PASSWORD)
        ->expectsQuestion('Repita a senha', STRONG_PASSWORD)
        ->expectsOutputToContain('Usuário criado: Caio Marcidele')
        ->assertSuccessful();

    $this->assertDatabaseHas('users', ['email' => 'caio@viacao.com', 'role' => 'admin']);
});

it('does not create the user when the password confirmation differs', function (): void {
    $this->artisan('erp:create-admin', ['--name' => 'Caio', '--email' => 'caio@viacao.com'])
        ->expectsQuestion('Senha (mínimo de 12 caracteres, com maiúsculas, minúsculas e números)', STRONG_PASSWORD)
        ->expectsQuestion('Repita a senha', STRONG_PASSWORD.'x')
        ->expectsOutput('As senhas não conferem.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
});

it('fails instead of asking when run without interaction and something is missing', function (array $options, string $message): void {
    $this->artisan('erp:create-admin', [...$options, '--no-interaction' => true])
        ->expectsOutput($message)
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'sem nome' => [['--email' => 'caio@viacao.com', '--password' => STRONG_PASSWORD], 'Informe --name (modo não interativo).'],
    'sem e-mail' => [['--name' => 'Caio', '--password' => STRONG_PASSWORD], 'Informe --email (modo não interativo).'],
    'sem senha' => [['--name' => 'Caio', '--email' => 'caio@viacao.com'], 'Informe --password (modo não interativo).'],
]);

it('rejects invalid input', function (array $overrides, string $message): void {
    $this->artisan('erp:create-user', [
        '--name' => 'Caio',
        '--email' => 'caio@viacao.com',
        '--password' => STRONG_PASSWORD,
        '--no-interaction' => true,
        ...$overrides,
    ])
        ->expectsOutputToContain($message)
        ->assertFailed();

    $this->assertDatabaseCount('users', 0);
})->with([
    'papel inexistente' => [['--role' => 'superuser'], 'Papel inválido. Use um destes: admin, dispatcher, fleet_manager, mechanic_supervisor.'],
    'e-mail inválido' => [['--email' => 'caio'], 'e-mail'],
    'senha curta' => [['--password' => 'Curta-1'], 'senha'],
    'senha sem números' => [['--password' => 'Senha-Sem-Numero'], 'senha'],
    'senha sem maiúsculas' => [['--password' => 'senha-fraca-2026'], 'senha'],
    'senha acima de 72' => [['--password' => 'Aa1'.str_repeat('x', 70)], 'senha'],
]);

it('refuses an e-mail that is already in use, in any case', function (): void {
    UserModel::factory()->create(['email' => 'caio@viacao.com']);

    $this->artisan('erp:create-admin', ['--name' => 'Caio', '--email' => 'CAIO@viacao.com', '--password' => STRONG_PASSWORD])
        ->expectsOutput('Já existe um usuário com este e-mail.')
        ->assertFailed();

    $this->assertDatabaseCount('users', 1);
});

it('audits the creation without the password or its hash', function (): void {
    $this->artisan('erp:create-admin', ['--name' => 'Caio Marcidele', '--email' => 'caio@viacao.com', '--password' => STRONG_PASSWORD])->assertSuccessful();

    $user = UserModel::query()->where('email', 'caio@viacao.com')->sole();
    $log = AuditLogModel::query()->sole();

    expect([$log->action, $log->auditable_type, $log->auditable_id, $log->old_values, $log->user_id])
        ->toBe(['user.created', 'user', $user->id, null, null])
        ->and($log->new_values)->toEqual(['name' => 'Caio Marcidele', 'email' => 'caio@viacao.com', 'role' => 'admin', 'channel' => 'console'])
        ->and(json_encode($log->getAttributes()))->not->toContain(STRONG_PASSWORD)
        ->and(json_encode($log->getAttributes()))->not->toContain($user->password);

    // Sem sessão no terminal, quem consulta a trilha vê a criação pelo nome da ação.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/audit-logs?search=usuario criado')
        ->assertOk()
        ->assertJsonPath('data.0.action_label', 'Usuário criado');
});
