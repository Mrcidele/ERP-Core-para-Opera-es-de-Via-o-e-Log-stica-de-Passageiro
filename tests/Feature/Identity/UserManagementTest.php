<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

const NEW_PASSWORD = 'Senha-Segura-2026';

beforeEach(function (): void {
    $this->admin = actingAsRole(UserRole::Admin);
});

function latestUserAudit(): AuditLogModel
{
    return AuditLogModel::query()->latest('created_at')->latest('id')->firstOrFail();
}

/*
| Listagem
*/

it('lists users with role label and status, paginated, never with the password', function (): void {
    // Nome fixo: o administrador da sessão fica por último na ordem alfabética.
    $this->admin->update(['name' => 'Zuleica Admin']);
    UserModel::factory()->role(UserRole::Dispatcher)->create(['name' => 'Bruna', 'email' => 'bruna@erp.test']);
    UserModel::factory()->role(UserRole::MechanicSupervisor)->create(['name' => 'Ana', 'email' => 'ana@erp.test', 'deactivated_at' => now()]);

    $response = $this->getJson('/api/v1/identity/users?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('data.0.name', 'Ana')
        ->assertJsonPath('data.0.role_label', 'Supervisão de oficina')
        ->assertJsonPath('data.0.active', false)
        ->assertJsonPath('data.1.email', 'bruna@erp.test')
        ->assertJsonPath('data.1.active', true)
        ->assertJsonPath('data.1.deactivated_at', null);

    expect($response->getContent())->not->toContain('password')->not->toContain('$argon2id$');
});

it('filters by status and searches name or e-mail', function (): void {
    UserModel::factory()->create(['name' => 'Bruna', 'email' => 'bruna@erp.test']);
    UserModel::factory()->create(['name' => 'Ana', 'email' => 'oficina@erp.test', 'deactivated_at' => now()]);

    $this->getJson('/api/v1/identity/users?status=inactive')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ana');
    $this->getJson('/api/v1/identity/users?search=OFICINA')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ana');
    $this->getJson('/api/v1/identity/users?status=archived')->assertUnprocessable()->assertJsonValidationErrors('status');
});

/*
| Criação
*/

it('creates a user with an Argon2id hash, who can then log in, and audits it from the web', function (): void {
    $this->postJson('/api/v1/identity/users', [
        'name' => 'Bruna Lima',
        'email' => 'Bruna@Transportadora.com',
        'role' => 'dispatcher',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'bruna@transportadora.com')
        ->assertJsonPath('data.role', 'dispatcher')
        ->assertJsonPath('data.active', true)
        ->assertJsonMissingPath('data.password');

    $user = UserModel::query()->where('email', 'bruna@transportadora.com')->sole();
    expect($user->getRawOriginal('password'))->toStartWith('$argon2id$');

    $log = latestUserAudit();
    expect($log->action)->toBe('user.created')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->new_values)->toEqual(['name' => 'Bruna Lima', 'email' => 'bruna@transportadora.com', 'role' => 'dispatcher', 'channel' => 'web'])
        ->and(json_encode($log->getAttributes()))->not->toContain(NEW_PASSWORD)->not->toContain('argon2');

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['email' => 'bruna@transportadora.com', 'password' => NEW_PASSWORD])->assertOk();
});

it('rejects a weak, unconfirmed or missing password and an invalid role', function (array $payload, string $field): void {
    $this->postJson('/api/v1/identity/users', [
        'name' => 'Bruna',
        'email' => 'bruna@erp.test',
        'role' => 'dispatcher',
        'password' => NEW_PASSWORD,
        'password_confirmation' => NEW_PASSWORD,
        ...$payload,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseMissing('users', ['email' => 'bruna@erp.test']);
})->with([
    'curta' => [['password' => 'Curta-1', 'password_confirmation' => 'Curta-1'], 'password'],
    'sem número' => [['password' => 'SenhaSemNumero', 'password_confirmation' => 'SenhaSemNumero'], 'password'],
    'não confirmada' => [['password_confirmation' => 'Outra-Senha-2026'], 'password'],
    'ausente' => [['password' => null, 'password_confirmation' => null], 'password'],
    'papel inexistente' => [['role' => 'superuser'], 'role'],
    'e-mail inválido' => [['email' => "bruna@erp.test\r\nBcc: x@y.z"], 'email'],
]);

it('refuses an e-mail already in use, in any letter case', function (): void {
    UserModel::factory()->create(['email' => 'bruna@erp.test']);

    $this->postJson('/api/v1/identity/users', [
        'name' => 'Outra Bruna', 'email' => 'BRUNA@erp.test', 'role' => 'dispatcher',
        'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD,
    ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Já existe um usuário com este e-mail.']);
});

/*
| Edição
*/

it('updates profile data and audits before and after, never the password', function (): void {
    $user = UserModel::factory()->role(UserRole::Dispatcher)->create(['name' => 'Bruna', 'email' => 'bruna@erp.test']);

    $this->putJson("/api/v1/identity/users/{$user->id}", ['name' => 'Bruna Lima', 'email' => 'bruna@erp.test', 'role' => 'fleet_manager'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bruna Lima')
        ->assertJsonPath('data.role', 'fleet_manager')
        ->assertJsonPath('data.role_label', 'Gestão de frota');

    $log = latestUserAudit();
    expect($log->action)->toBe('user.updated')
        ->and($log->auditable_id)->toBe($user->id)
        ->and($log->old_values)->toEqual(['name' => 'Bruna', 'role' => 'dispatcher'])
        ->and($log->new_values)->toEqual(['name' => 'Bruna Lima', 'role' => 'fleet_manager']);
});

it('resets the password: the old one stops working, sessions end and the audit only says it was reset', function (): void {
    $user = UserModel::factory()->role(UserRole::Dispatcher)->create(['email' => 'bruna@erp.test']);
    $user->createToken('antigo');

    $this->putJson("/api/v1/identity/users/{$user->id}", [
        'name' => $user->name, 'email' => 'bruna@erp.test', 'role' => 'dispatcher',
        'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD,
    ])->assertOk();

    expect($user->tokens()->count())->toBe(0)
        ->and(latestUserAudit()->new_values)->toBe(['password_reset' => true])
        ->and(json_encode(latestUserAudit()->getAttributes()))->not->toContain(NEW_PASSWORD);

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['email' => 'bruna@erp.test', 'password' => 'Password@123'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', ['email' => 'bruna@erp.test', 'password' => NEW_PASSWORD])->assertOk();
});

it('keeps the password when the field is left empty', function (): void {
    $user = UserModel::factory()->create(['email' => 'bruna@erp.test']);
    $hash = $user->getRawOriginal('password');

    $this->putJson("/api/v1/identity/users/{$user->id}", ['name' => 'Bruna', 'email' => 'bruna@erp.test', 'role' => 'dispatcher', 'password' => ''])->assertOk();

    expect($user->fresh()?->getRawOriginal('password'))->toBe($hash);
});

it('does not let the last active administrator demote themselves', function (): void {
    $this->putJson("/api/v1/identity/users/{$this->admin->id}", ['name' => 'Eu', 'email' => $this->admin->email, 'role' => 'dispatcher'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role' => 'A empresa precisa de pelo menos um administrador ativo. Promova outro usuário a administrador antes.']);

    expect($this->admin->fresh()?->role)->toBe(UserRole::Admin);
});

it('lets an administrator step down when another administrator is active', function (): void {
    UserModel::factory()->role(UserRole::Admin)->create();

    $this->putJson("/api/v1/identity/users/{$this->admin->id}", ['name' => 'Eu', 'email' => $this->admin->email, 'role' => 'dispatcher'])->assertOk();
});

it('answers 404 for an unknown or malformed user id', function (string $id): void {
    $this->putJson("/api/v1/identity/users/{$id}", ['name' => 'X', 'email' => 'x@erp.test', 'role' => 'admin'])->assertNotFound();
    $this->postJson("/api/v1/identity/users/{$id}/deactivate")->assertNotFound();
})->with(['0193a5b0-7c1e-7000-8000-000000000000', 'nao-e-uuid']);

/*
| Ativar e desativar
*/

it('deactivates a user: login refused, tokens gone, audited', function (): void {
    $user = UserModel::factory()->role(UserRole::Dispatcher)->create(['email' => 'bruna@erp.test']);
    $token = $user->createToken('sessao')->plainTextToken;

    $this->postJson("/api/v1/identity/users/{$user->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.active', false);

    expect($user->tokens()->count())->toBe(0)
        ->and(latestUserAudit()->action)->toBe('user.deactivated')
        ->and(latestUserAudit()->user_id)->toBe($this->admin->id);

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->postJson('/api/v1/auth/login', ['email' => 'bruna@erp.test', 'password' => 'Password@123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'E-mail ou senha incorretos.']);
});

it('never accepts a token of a deactivated user, even one that escaped revocation', function (): void {
    $user = UserModel::factory()->create();
    $token = $user->createToken('sessao')->plainTextToken;
    DB::table('users')->where('id', $user->id)->update(['deactivated_at' => now()]);

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('reactivates a user, who can log in again, and audits it', function (): void {
    $user = UserModel::factory()->create(['email' => 'bruna@erp.test', 'deactivated_at' => now()]);

    $this->postJson("/api/v1/identity/users/{$user->id}/reactivate")->assertOk()->assertJsonPath('data.active', true);

    expect(latestUserAudit()->action)->toBe('user.reactivated');

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/auth/login', ['email' => 'bruna@erp.test', 'password' => 'Password@123'])->assertOk();
});

it('does not let an administrator deactivate their own access', function (): void {
    UserModel::factory()->role(UserRole::Admin)->create();

    $this->postJson("/api/v1/identity/users/{$this->admin->id}/deactivate")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user' => 'Você não pode desativar o próprio acesso.']);
});

it('keeps one active administrator even against a direct write to the database', function (): void {
    expect(fn () => DB::table('users')->where('id', $this->admin->id)->update(['role' => 'dispatcher']))
        ->toThrow(QueryException::class, 'last_active_admin');
});

/*
| Acesso
*/

it('is closed to every profile except administration', function (UserRole $role): void {
    actingAsRole($role);
    $target = UserModel::factory()->create();

    $this->getJson('/api/v1/identity/users')->assertForbidden();
    $this->postJson('/api/v1/identity/users', ['name' => 'X', 'email' => 'x@erp.test', 'role' => 'admin', 'password' => NEW_PASSWORD, 'password_confirmation' => NEW_PASSWORD])->assertForbidden();
    $this->putJson("/api/v1/identity/users/{$target->id}", ['name' => 'X', 'email' => 'x@erp.test', 'role' => 'admin'])->assertForbidden();
    $this->postJson("/api/v1/identity/users/{$target->id}/deactivate")->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'x@erp.test']);
    expect($target->fresh()?->role)->not->toBe(UserRole::Admin);
})->with([UserRole::Dispatcher, UserRole::FleetManager, UserRole::MechanicSupervisor]);
