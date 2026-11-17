<?php

declare(strict_types=1);

use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

const STRONG_PASSWORD = 'Viacao-Segura-2026';

/**
 * Roda o comando sem interação, com a senha chegando pela entrada padrão (`--password-stdin`).
 *
 * @param  array<string, string|bool>  $options
 * @return array{int, string} código de saída e o que o comando escreveu
 */
function createUserWithStdin(array $options, string $stdin = STRONG_PASSWORD.PHP_EOL, string $command = 'erp:create-user'): array
{
    $stream = fopen('php://memory', 'r+');
    assert(is_resource($stream));
    fwrite($stream, $stdin);
    rewind($stream);

    $input = new ArrayInput(['command' => $command, '--password-stdin' => true, ...$options]);
    $input->setStream($stream);
    $input->setInteractive(false);
    $output = new BufferedOutput;

    $code = app(Kernel::class)->handle($input, $output);

    return [$code, $output->fetch()];
}

it('creates an administrator with the password from stdin and lets them log in', function (): void {
    [$code, $output] = createUserWithStdin(['--name' => 'Caio Marcidele', '--email' => 'Caio@Viacao.com'], command: 'erp:create-admin');

    expect($code)->toBe(0)
        ->and($output)->toContain('Usuário criado: Caio Marcidele <caio@viacao.com>, papel Administração.');

    $user = UserModel::query()->where('email', 'caio@viacao.com')->sole();

    expect($user->role->value)->toBe('admin')
        ->and(Hash::check(STRONG_PASSWORD, $user->password))->toBeTrue();

    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/auth/login', ['email' => 'caio@viacao.com', 'password' => STRONG_PASSWORD])
        ->assertOk()
        ->assertJsonPath('data.user.role', 'admin');
});

it('creates a user with another role under its full name', function (): void {
    [$code, $output] = createUserWithStdin(['--name' => 'Diego Despacho', '--email' => 'diego@viacao.com', '--role' => 'dispatcher']);

    expect($code)->toBe(0)
        ->and($output)->toContain('Usuário criado: Diego Despacho <diego@viacao.com>, papel Despacho.');

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
    'sem nome' => [['--email' => 'caio@viacao.com', '--password-stdin' => true], 'Informe --name (modo não interativo).'],
    'sem e-mail' => [['--name' => 'Caio', '--password-stdin' => true], 'Informe --email (modo não interativo).'],
    'sem senha' => [['--name' => 'Caio', '--email' => 'caio@viacao.com'], 'Informe a senha pelo prompt interativo ou por --password-stdin (modo não interativo).'],
]);

it('does not accept the password as a command-line argument', function (): void {
    // Argumento fica no histórico do shell e aparece em `ps`: a opção nem existe.
    expect(fn () => $this->artisan('erp:create-admin', ['--name' => 'Caio', '--email' => 'caio@viacao.com', '--password' => STRONG_PASSWORD]))
        ->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('users', 0);
});

it('fails when stdin brings no password', function (): void {
    [$code, $output] = createUserWithStdin(['--name' => 'Caio', '--email' => 'caio@viacao.com'], stdin: '');

    expect($code)->toBe(1)->and($output)->toContain('Nenhuma senha recebida pela entrada padrão.');
    $this->assertDatabaseCount('users', 0);
});

it('rejects invalid input', function (array $overrides, string $password, string $message): void {
    [$code, $output] = createUserWithStdin(['--name' => 'Caio', '--email' => 'caio@viacao.com', ...$overrides], $password.PHP_EOL);

    expect($code)->toBe(1)->and($output)->toContain($message);
    $this->assertDatabaseCount('users', 0);
})->with([
    'papel inexistente' => [['--role' => 'superuser'], STRONG_PASSWORD, 'Papel inválido. Use um destes: admin, dispatcher, fleet_manager, mechanic_supervisor.'],
    'e-mail inválido' => [['--email' => 'caio'], STRONG_PASSWORD, 'e-mail'],
    'e-mail com quebra de linha' => [['--email' => "caio@viacao.com\r\nBcc: x@y.com"], STRONG_PASSWORD, 'e-mail'],
    'senha curta' => [[], 'Curta-1', 'senha'],
    'senha sem números' => [[], 'Senha-Sem-Numero', 'senha'],
    'senha sem maiúsculas' => [[], 'senha-fraca-2026', 'senha'],
    'senha acima de 128' => [[], 'Aa1'.str_repeat('x', 126), 'senha'],
]);

it('refuses an e-mail that is already in use, in any case', function (): void {
    UserModel::factory()->create(['email' => 'caio@viacao.com']);

    [$code, $output] = createUserWithStdin(['--name' => 'Caio', '--email' => 'CAIO@viacao.com']);

    expect($code)->toBe(1)->and($output)->toContain('Já existe um usuário com este e-mail.');

    $this->assertDatabaseCount('users', 1);
});

it('audits the creation without the password or its hash', function (): void {
    expect(createUserWithStdin(['--name' => 'Caio Marcidele', '--email' => 'caio@viacao.com'])[0])->toBe(0);

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
