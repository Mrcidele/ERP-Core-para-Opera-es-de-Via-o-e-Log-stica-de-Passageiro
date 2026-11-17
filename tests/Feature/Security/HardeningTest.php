<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\UserModel;
use App\Infrastructure\Support\InsecureConfigurationGuard;
use App\Infrastructure\Support\TrustedProxies;
use App\Presentation\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

// A página do SPA não depende do build do Vite nos testes (o CI roda sem public/build).
beforeEach(fn () => $this->withoutVite());

afterEach(fn () => TrustedProxies::apply(null));

/*
| Escalonamento de privilégio e IDOR
*/

it('does not let a login payload choose the role or the account', function (): void {
    $user = UserModel::factory()->role(UserRole::MechanicSupervisor)->create(['email' => 'oficina@erp.test']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'oficina@erp.test',
        'password' => 'Password@123',
        'role' => 'admin',
        'is_admin' => true,
        'id' => UserModel::factory()->role(UserRole::Admin)->create()->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.role', 'mechanic_supervisor')
        ->assertJsonMissingPath('data.permissions.audit.view');

    expect($user->fresh()?->role)->toBe(UserRole::MechanicSupervisor);
});

it('treats a deleted record as missing even for whoever can manage it', function (): void {
    actingAsRole(UserRole::FleetManager);

    $id = $this->postJson('/api/v1/fleet/vehicles', hardeningVehicle())->assertCreated()->json('data.id');
    $this->deleteJson("/api/v1/fleet/vehicles/{$id}")->assertNoContent();

    $this->getJson("/api/v1/fleet/vehicles/{$id}")->assertNotFound();
    $this->putJson("/api/v1/fleet/vehicles/{$id}", hardeningVehicle(['status' => 'active']))->assertNotFound();
});

it('answers 404 for an id that is not a UUID instead of leaking a database error', function (): void {
    actingAsRole(UserRole::Admin);

    $this->getJson("/api/v1/fleet/vehicles/1' OR '1'='1")->assertNotFound();
});

/*
| Mass assignment
*/

it('ignores protected fields sent along with a valid payload', function (): void {
    actingAsRole(UserRole::FleetManager);

    $response = $this->postJson('/api/v1/fleet/vehicles', hardeningVehicle([
        'id' => '00000000-0000-0000-0000-000000000001',
        'status' => 'active',
        'created_at' => '2000-01-01T00:00:00Z',
        'deleted_at' => '2000-01-01T00:00:00Z',
        'role' => 'admin',
    ]))->assertCreated();

    expect($response->json('data.id'))->not->toBe('00000000-0000-0000-0000-000000000001');
    $this->assertDatabaseHas('vehicles', ['id' => $response->json('data.id'), 'deleted_at' => null]);
});

/*
| Senhas e segredos
*/

it('never returns the password hash or a remember token in JSON', function (): void {
    $user = UserModel::factory()->role(UserRole::Admin)->create(['email' => 'admin@erp.test']);

    $login = $this->postJson('/api/v1/auth/login', ['email' => 'admin@erp.test', 'password' => 'Password@123'])->assertOk();
    $me = $this->withToken($login->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();

    foreach ([$login->getContent(), $me->getContent()] as $body) {
        expect($body)->not->toContain('password')
            ->and($body)->not->toContain('remember_token')
            ->and($body)->not->toContain((string) $user->getRawOriginal('password'));
    }
});

it('stores new passwords with Argon2id', function (): void {
    $user = UserModel::factory()->create();

    expect($user->getRawOriginal('password'))->toStartWith('$argon2id$');
});

it('still accepts an account hashed with bcrypt and upgrades it to Argon2id on login', function (): void {
    $user = UserModel::factory()->create(['email' => 'antigo@erp.test']);
    DB::table('users')->where('id', $user->id)->update(['password' => Hash::driver('bcrypt')->make('Password@123')]);

    $this->postJson('/api/v1/auth/login', ['email' => 'antigo@erp.test', 'password' => 'Password@123'])->assertOk();

    expect(DB::table('users')->where('id', $user->id)->value('password'))->toStartWith('$argon2id$');
});

it('does not touch the stored hash when the bcrypt password is wrong', function (): void {
    $user = UserModel::factory()->create(['email' => 'antigo@erp.test']);
    $legacy = Hash::driver('bcrypt')->make('Password@123');
    DB::table('users')->where('id', $user->id)->update(['password' => $legacy]);

    $this->postJson('/api/v1/auth/login', ['email' => 'antigo@erp.test', 'password' => 'Errada@1234'])->assertUnprocessable();

    expect(DB::table('users')->where('id', $user->id)->value('password'))->toBe($legacy);
});

/*
| Limite de tentativas
*/

it('blocks one IP that tries many different accounts', function (): void {
    foreach (range(1, 20) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => "conta{$attempt}@erp.test", 'password' => 'Errada@1234'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'outra@erp.test', 'password' => 'Errada@1234'])->assertTooManyRequests();

    RateLimiter::clear('ip:127.0.0.1');
});

it('takes the real client IP behind Render and Cloudflare, ignoring what the client forged', function (): void {
    TrustedProxies::apply('render');

    // O cliente forjou 1.2.3.4; a Cloudflare acrescentou o IP real (203.0.113.9) e o próprio (104.16.0.1).
    $this->withServerVariables(['REMOTE_ADDR' => '10.21.157.68'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4, 203.0.113.9, 104.16.0.1'])
        ->postJson('/api/v1/auth/login', ['email' => 'ninguem@erp.test', 'password' => 'Errada@1234'])
        ->assertUnprocessable();

    expect(AuditLogModel::query()->latest('created_at')->firstOrFail()->ip_address)->toBe('203.0.113.9');
});

it('does not trust forwarded headers from an address outside the Render network', function (): void {
    TrustedProxies::apply('render');

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->postJson('/api/v1/auth/login', ['email' => 'ninguem@erp.test', 'password' => 'Errada@1234'])
        ->assertUnprocessable();

    expect(AuditLogModel::query()->latest('created_at')->firstOrFail()->ip_address)->toBe('198.51.100.7');
});

/*
| Trilha de auditoria somente inserção
*/

it('refuses to change or delete an audit record through the model', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@erp.test', 'password' => 'Errada@1234']);
    $log = AuditLogModel::query()->sole();

    expect(fn () => $log->update(['action' => 'auth.login']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('refuses UPDATE, DELETE and TRUNCATE on audit_logs in the database itself', function (string $sql): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@erp.test', 'password' => 'Errada@1234']);

    expect(fn () => DB::statement($sql))->toThrow(QueryException::class, 'somente inserção');
})->with([
    'update' => "UPDATE audit_logs SET action = 'x'",
    'delete' => 'DELETE FROM audit_logs',
    'truncate' => 'TRUNCATE audit_logs',
]);

it('has only read routes for the audit trail', function (): void {
    $methods = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/audit-logs'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()->sort()->values()->all();

    expect($methods)->toBe(['GET', 'HEAD']);
});

/*
| Cabeçalhos HTTP
*/

it('sends a strict CSP and the browser policies on the SPA page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', SecurityHeaders::CONTENT_SECURITY_POLICY)
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        ->assertHeader('X-Frame-Options', 'DENY');

    expect(SecurityHeaders::CONTENT_SECURITY_POLICY)->not->toContain('unsafe-eval')
        ->and(SecurityHeaders::CONTENT_SECURITY_POLICY)->not->toContain('unsafe-inline');
});

it('sends HSTS only over HTTPS', function (): void {
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});

it('does not allow credentials or arbitrary origins through CORS', function (): void {
    $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/v1/auth/login')
        ->assertHeaderMissing('Access-Control-Allow-Origin')
        ->assertHeaderMissing('Access-Control-Allow-Credentials');
});

/*
| Arquivos sensíveis
*/

it('answers 404 for hidden and project files instead of the SPA page', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with(['/.env', '/.git/config', '/.env.example', '/composer.json', '/package.json', '/database/database.sqlite', '/storage/logs/laravel.log', '/vendor/autoload.php']);

/*
| Configuração de produção
*/

it('refuses to boot production with debug on or without a valid key', function (mixed $debug, mixed $key): void {
    expect(fn () => InsecureConfigurationGuard::check('production', $debug, $key))->toThrow(RuntimeException::class);
})->with([
    'debug ligado' => [true, 'base64:'.base64_encode(str_repeat('a', 32))],
    'sem chave' => [false, null],
    'chave vazia' => [false, ''],
    'chave curta' => [false, 'base64:'.base64_encode('curta')],
    'base64 inválido' => [false, 'base64:%%%'],
]);

it('boots production with debug off and a 32-byte key, and ignores other environments', function (): void {
    InsecureConfigurationGuard::check('production', false, 'base64:'.base64_encode(random_bytes(32)));
    InsecureConfigurationGuard::check('local', true, null);

    expect(true)->toBeTrue();
});

it('applies the statement timeout to new PostgreSQL connections', function (): void {
    expect(statementTimeoutWith('15s'))->toBe('15s');
});

it('ignores a statement timeout that is not a plain duration', function (): void {
    expect(statementTimeoutWith("1s'; DROP TABLE users; --"))->not->toBe('1s')
        ->and(DB::getSchemaBuilder()->hasTable('users'))->toBeTrue();
});

it('does not open the connection just to set the timeout, so an unreachable database stays a query error', function (): void {
    config(['database.connections.pgsql_down' => [...config('database.connections.pgsql'), 'port' => 1, 'statement_timeout' => '15s']]);

    try {
        // Criar o objeto de conexão (o cache e o limitador fazem isso no boot) não pode conectar.
        $connection = DB::connection('pgsql_down');

        expect(fn () => $connection->select('SELECT 1'))->toThrow(QueryException::class);
    } finally {
        DB::purge('pgsql_down');
    }
});

/** Abre uma conexão nova com o `statement_timeout` informado e devolve o valor que o PostgreSQL aplicou. */
function statementTimeoutWith(string $timeout): string
{
    config(['database.connections.pgsql_timeout' => [...config('database.connections.pgsql'), 'statement_timeout' => $timeout]]);

    try {
        return (string) DB::connection('pgsql_timeout')->selectOne('SHOW statement_timeout')->statement_timeout;
    } finally {
        DB::purge('pgsql_timeout');
    }
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function hardeningVehicle(array $overrides = []): array
{
    return [
        'plate' => 'SEG1A23',
        'chassis' => '9BWZZZ377VT004299',
        'capacity' => 44,
        'class_type' => 'executivo',
        'current_mileage' => 1000,
        ...$overrides,
    ];
}
