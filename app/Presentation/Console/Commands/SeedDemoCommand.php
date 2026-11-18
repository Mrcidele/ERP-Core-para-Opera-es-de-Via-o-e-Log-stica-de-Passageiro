<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Domain\Identity\Actions\CreateUserAction;
use App\Domain\Identity\Actions\ReactivateUserAction;
use App\Domain\Identity\Actions\UpdateUserAction;
use App\Domain\Identity\DTOs\CreateUserDTO;
use App\Domain\Identity\DTOs\UpdateUserDTO;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Contracts\Clock;
use App\Presentation\Http\Requests\Identity\UserRules;
use Database\Seeders\DemoScenarioSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * Monta o ambiente de demonstração comercial: a conta demo@frotacarga.com.br, com papel Gestão de frota, e a frota
 * de exemplo do `DemoScenarioSeeder`. Pode rodar de novo antes de cada apresentação: refaz o histórico da demonstração
 * a partir da data atual e não toca em nenhuma outra conta nem nos demais registros.
 *
 * A senha nunca vem por argumento: é digitada no prompt (duas vezes, sem eco), lida da entrada padrão
 * (`--password-stdin`) ou gerada e mostrada uma única vez (`--generate-password`). Se a conta já existe e nenhuma
 * dessas opções é usada, a senha atual é mantida.
 */
final class SeedDemoCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'erp:seed-demo
        {--generate-password : Gera uma senha forte e mostra no terminal uma única vez}
        {--password-stdin : Lê a senha da primeira linha da entrada padrão (automação)}
        {--force : Roda em produção sem pedir confirmação}';

    protected $description = 'Cria ou atualiza a conta de demonstração e a frota de exemplo para apresentações';

    private const string DEMO_NAME = 'Demonstração FrotaCarga';

    public function handle(
        UserRepository $users,
        CreateUserAction $createUser,
        UpdateUserAction $updateUser,
        ReactivateUserAction $reactivateUser,
        DemoScenarioSeeder $scenario,
        Clock $clock,
    ): int {
        if (! $this->confirmToProceed('O ambiente é de produção: a frota de demonstração será gravada neste banco.')) {
            return self::FAILURE;
        }

        $existing = $users->findByEmail(DemoScenarioSeeder::DEMO_EMAIL);

        // A conta de demonstração nunca vira (nem rebaixa) um administrador.
        if ($existing !== null && $existing->role === UserRole::Admin) {
            $this->error('A conta '.DemoScenarioSeeder::DEMO_EMAIL.' é de Administração e não será alterada. Use outra conta para a demonstração.');

            return self::FAILURE;
        }

        $generated = $this->option('generate-password') === true;
        $password = $this->password($existing, $generated);

        if ($password === false) {
            return self::FAILURE;
        }

        if ($password !== null) {
            $validator = Validator::make(['password' => $password], ['password' => UserRules::password()], [], ['password' => 'senha']);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }
        }

        $user = $existing === null
            ? $createUser->execute(new CreateUserDTO(self::DEMO_NAME, DemoScenarioSeeder::DEMO_EMAIL, UserRole::FleetManager, (string) $password), 'console')
            : $updateUser->execute(new UpdateUserDTO($existing->id, self::DEMO_NAME, DemoScenarioSeeder::DEMO_EMAIL, UserRole::FleetManager, $password));

        if (! $user->isActive()) {
            $user = $reactivateUser->execute($user->id);
        }

        $scenario->run($clock);

        $this->info(sprintf(
            'Conta de demonstração %s: %s, papel %s.',
            $existing === null ? 'criada' : 'atualizada',
            $user->email,
            $user->role->label(),
        ));
        $this->line(sprintf('Frota de demonstração pronta: %d caminhões, 2 com consumo abaixo da meta, revisões vencendo e escala das próximas 48 horas.', count(DemoScenarioSeeder::VEHICLES)));

        if ($generated) {
            $this->newLine();
            $this->warn('Senha gerada (não fica gravada em lugar nenhum; anote agora):');
            $this->line((string) $password);
        } elseif ($password === null) {
            $this->line('Senha mantida.');
        }

        return self::SUCCESS;
    }

    /**
     * Senha nova, null para manter a atual (só quando a conta já existe) ou false em caso de erro.
     */
    private function password(?User $existing, bool $generate): string|false|null
    {
        if ($generate) {
            // Sorteia até ter maiúscula, minúscula e número, como a regra de senha exige.
            do {
                $password = Str::password(20, symbols: false);
            } while (preg_match('/[A-Z]/', $password) !== 1 || preg_match('/[a-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1);

            return $password;
        }

        if ($this->option('password-stdin') === true) {
            $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
            $line = fgets(is_resource($stream) ? $stream : STDIN);
            $password = is_string($line) ? rtrim($line, "\r\n") : '';

            if ($password === '') {
                $this->error('Nenhuma senha recebida pela entrada padrão.');

                return false;
            }

            return $password;
        }

        if ($existing !== null) {
            return null;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Informe a senha da conta de demonstração: use --generate-password ou --password-stdin (modo não interativo).');

            return false;
        }

        $password = self::text($this->secret('Senha da conta de demonstração (mínimo de 12 caracteres, com maiúsculas, minúsculas e números)'));
        $confirmation = self::text($this->secret('Repita a senha'));

        if (! hash_equals($password, $confirmation)) {
            $this->error('As senhas não conferem.');

            return false;
        }

        return $password;
    }

    private static function text(mixed $answer): string
    {
        return is_string($answer) ? $answer : '';
    }
}
