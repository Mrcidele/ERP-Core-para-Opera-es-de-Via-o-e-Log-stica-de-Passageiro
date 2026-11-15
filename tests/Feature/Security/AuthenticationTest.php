<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->user = UserModel::factory()->role(UserRole::Dispatcher)->create([
        'name' => 'Diego Despachante',
        'email' => 'despachante@erp.test',
    ]);
});

/** Faz o login e devolve o token. */
function loginToken(string $email = 'despachante@erp.test', string $password = 'Password@123'): string
{
    return test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])->assertOk()->json('data.token');
}

/** Cada requisição de um teste reaproveita a aplicação; isto descarta o usuário já resolvido pelo guard. */
function forgetAuthenticatedUser(): void
{
    app('auth')->forgetGuards();
}

it('logs in and returns the token, the user and the permissions', function (): void {
    $response = $this->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'Password@123']);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role'], 'permissions']])
        ->assertJsonPath('data.user', [
            'id' => $this->user->id,
            'name' => 'Diego Despachante',
            'email' => 'despachante@erp.test',
            'role' => 'dispatcher',
        ])
        ->assertJsonPath('data.permissions', ['fleet.view', 'crew.view', 'operations.view', 'operations.manage']);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty()
        ->and($response->getContent())->not->toContain('password');
});

it('accepts the e-mail in any letter case', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => ' Despachante@ERP.test ', 'password' => 'Password@123'])->assertOk();
});

it('authenticates later requests with the issued token', function (): void {
    $token = loginToken();
    forgetAuthenticatedUser();

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.email', 'despachante@erp.test')
        ->assertJsonPath('data.permissions', ['fleet.view', 'crew.view', 'operations.view', 'operations.manage']);
});

it('stores only a hash of the token', function (): void {
    $token = loginToken();

    $stored = DB::table('personal_access_tokens')->value('token');

    expect($stored)->not->toBe($token)
        ->and($token)->not->toContain((string) $stored)
        ->and(DB::table('personal_access_tokens')->value('tokenable_id'))->toBe($this->user->id);
});

it('rejects wrong credentials with the same message for a wrong password and an unknown e-mail', function (string $email, string $password): void {
    $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.')
        ->assertJsonMissingPath('data');

    $this->assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'wrong password' => ['despachante@erp.test', 'senha-errada'],
    'unknown e-mail' => ['ninguem@erp.test', 'Password@123'],
]);

it('rejects malformed login input', function (array $payload, string $field): void {
    $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing e-mail' => [['password' => 'x'], 'email'],
    'malformed e-mail' => [['email' => 'nao-e-email', 'password' => 'x'], 'email'],
    'e-mail with a line break' => [['email' => "a@erp.test\r\nBcc: x@evil.test", 'password' => 'x'], 'email'],
    'missing password' => [['email' => 'a@erp.test'], 'password'],
]);

it('rejects a deleted user', function (): void {
    $this->user->delete();

    $this->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'Password@123'])->assertUnprocessable();
});

it('revokes the previous token when the user logs in again', function (): void {
    $old = loginToken();
    $new = loginToken();
    forgetAuthenticatedUser();

    $this->withToken($old)->getJson('/api/v1/auth/me')->assertUnauthorized();
    forgetAuthenticatedUser();
    $this->withToken($new)->getJson('/api/v1/auth/me')->assertOk();
});

it('logs out by revoking the current token', function (): void {
    $token = loginToken();
    forgetAuthenticatedUser();

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->assertDatabaseCount('personal_access_tokens', 0);

    forgetAuthenticatedUser();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects an expired token', function (): void {
    $token = loginToken();
    forgetAuthenticatedUser();

    $this->travel(config('sanctum.expiration') + 1)->minutes();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects a token that was never issued', function (): void {
    $this->withToken('1|token-inventado')->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Não autenticado. Entre no sistema para continuar.']);
});

it('blocks the login after 5 attempts per minute for the same e-mail and IP', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'errada'])->assertUnprocessable();
    }

    // Nem a senha certa passa enquanto o bloqueio durar.
    $this->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'Password@123'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Muitas tentativas. Aguarde um minuto e tente de novo.')
        ->assertHeader('Retry-After');

    // O bloqueio é por e-mail + IP: outra conta, ou a mesma conta de outro IP, continua podendo entrar.
    UserModel::factory()->create(['email' => 'outra@erp.test']);
    $this->postJson('/api/v1/auth/login', ['email' => 'outra@erp.test', 'password' => 'Password@123'])->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'Password@123'])
        ->assertOk();

    $this->travel(61)->seconds();
    $this->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'Password@123'])->assertOk();
});

it('counts the e-mail in any letter case as the same account for the limit', function (): void {
    foreach (['a@erp.test', 'A@erp.test', 'a@ERP.test', ' a@erp.test', 'A@ERP.TEST'] as $email) {
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'errada'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'a@erp.test', 'password' => 'errada'])->assertTooManyRequests();
});
