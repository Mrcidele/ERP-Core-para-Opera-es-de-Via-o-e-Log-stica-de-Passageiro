<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Domain\Identity\Actions\CreateUserAction;
use App\Domain\Identity\DTOs\CreateUserDTO;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Shared\Exceptions\DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Cria uma conta de acesso pelo terminal. É o caminho para o primeiro administrador em produção,
 * onde o seeder não cria usuários e a tela não tem cadastro de usuários.
 *
 * Sem as opções, pergunta nome, e-mail e senha (a senha sem eco, digitada duas vezes).
 * `--password` funciona, mas a senha fica no histórico do shell: prefira o modo interativo.
 */
final class CreateUserCommand extends Command
{
    protected $signature = 'erp:create-user
        {--name= : Nome de quem vai usar a conta}
        {--email= : E-mail de login}
        {--password= : Senha (fica no histórico do shell; sem esta opção, ela é pedida sem eco)}
        {--role=admin : Papel: admin, dispatcher, fleet_manager ou mechanic_supervisor}';

    protected $description = 'Cria uma conta de acesso ao ERP (padrão: administrador)';

    /** @var list<string> */
    protected $aliases = ['erp:create-admin'];

    public function handle(CreateUserAction $createUser): int
    {
        $name = $this->valueOf('name', fn (): string => self::text($this->ask('Nome')));
        $email = $this->valueOf('email', fn (): string => self::text($this->ask('E-mail')));
        $role = self::text($this->option('role'));
        $password = $this->passwordFromInput();

        if ($name === null || $email === null || $password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'role' => $role, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email:rfc', 'max:255'],
                'role' => ['required', Rule::enum(UserRole::class)],
                // 72 bytes é o limite do bcrypt: o que passar disso seria ignorado sem aviso.
                'password' => ['required', 'string', 'max:72', Password::min(12)->letters()->mixedCase()->numbers()],
            ],
            ['role' => 'Papel inválido. Use um destes: '.implode(', ', array_column(UserRole::cases(), 'value')).'.'],
            ['name' => 'nome', 'email' => 'e-mail', 'role' => 'papel', 'password' => 'senha'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            $user = $createUser->execute(new CreateUserDTO($name, $email, UserRole::from($role), $password), 'console');
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Usuário criado: {$user->name} <{$user->email}>, papel {$user->role->label()}.");

        return self::SUCCESS;
    }

    /**
     * Valor da opção; sem ela, pergunta (modo interativo) ou falha (com --no-interaction).
     *
     * @param  callable(): string  $ask
     */
    private function valueOf(string $option, callable $ask): ?string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (! $this->input->isInteractive()) {
            $this->error("Informe --{$option} (modo não interativo).");

            return null;
        }

        return $ask();
    }

    private function passwordFromInput(): ?string
    {
        $password = $this->option('password');

        if (is_string($password) && $password !== '') {
            return $password;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Informe --password (modo não interativo).');

            return null;
        }

        $password = self::text($this->secret('Senha (mínimo de 12 caracteres, com maiúsculas, minúsculas e números)'));
        $confirmation = self::text($this->secret('Repita a senha'));

        if (! hash_equals($password, $confirmation)) {
            $this->error('As senhas não conferem.');

            return null;
        }

        return $password;
    }

    /** Resposta do terminal como texto; vazio quando não veio nada. */
    private static function text(mixed $answer): string
    {
        return is_string($answer) ? $answer : '';
    }
}
