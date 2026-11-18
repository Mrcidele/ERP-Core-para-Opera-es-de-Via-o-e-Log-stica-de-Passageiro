<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\FuelLogModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\UserModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

const DEMO_PASSWORD = 'Demonstracao-Frota-2026';

/**
 * Roda `erp:seed-demo` sem interação.
 *
 * @param  array<string, bool>  $options
 * @return array{int, string} código de saída e o que o comando escreveu
 */
function seedDemo(array $options = [], ?string $stdin = null): array
{
    $input = new ArrayInput(['command' => 'erp:seed-demo', ...$options]);

    if ($stdin !== null) {
        $stream = fopen('php://memory', 'r+');
        assert(is_resource($stream));
        fwrite($stream, $stdin);
        rewind($stream);
        $input->setStream($stream);
    }

    $input->setInteractive(false);
    $output = new BufferedOutput;

    $code = app(Kernel::class)->handle($input, $output);

    return [$code, $output->fetch()];
}

it('creates the demo account as fleet manager with a generated password shown once', function (): void {
    [$code, $output] = seedDemo(['--generate-password' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain('Conta de demonstração criada: demo@frotacarga.com.br, papel Gestão de frota.')
        ->and($output)->toContain('Senha gerada');

    $password = trim((string) last(array_filter(explode("\n", trim($output)))));
    $user = UserModel::query()->where('email', 'demo@frotacarga.com.br')->sole();

    expect($user->role)->toBe(UserRole::FleetManager)
        ->and(strlen($password))->toBe(20)
        ->and(Hash::check($password, $user->password))->toBeTrue()
        // A senha não vai para a auditoria.
        ->and(AuditLogModel::query()->get()->toJson())->not->toContain($password);
});

it('seeds a cargo fleet with two trucks below their fuel efficiency target', function (): void {
    seedDemo(['--password-stdin' => true], DEMO_PASSWORD.PHP_EOL);

    expect(VehicleModel::query()->count())->toBe(9)
        ->and(FuelLogModel::query()->count())->toBe(45)
        ->and(EmployeeModel::query()->count())->toBe(7)
        ->and(MaintenanceOrderModel::query()->where('status', 'open')->count())->toBe(1)
        ->and(TripModel::query()->where('status', 'in_progress')->count())->toBe(1)
        ->and(TripModel::query()->where('status', 'scheduled')->count())->toBe(5);

    actingAsRole(UserRole::FleetManager);
    $logs = $this->getJson('/api/v1/fuel/logs?per_page=100')->assertOk()->json('data');

    $alerts = collect($logs)->where('low_efficiency_alert', true)->pluck('vehicle.plate')->unique()->sort()->values()->all();
    expect($alerts)->toBe(['FRT6G04', 'RHF2C19']);

    // Revisão preventiva: duas já passaram do ponto e três vencem nos próximos mil quilômetros.
    $vehicles = VehicleModel::query()->get();
    expect($vehicles->filter(fn (VehicleModel $v): bool => $v->current_mileage >= $v->next_preventive_maintenance_km))->toHaveCount(2)
        ->and($vehicles->filter(fn (VehicleModel $v): bool => $v->next_preventive_maintenance_km - $v->current_mileage > 0 && $v->next_preventive_maintenance_km - $v->current_mileage <= 1_000))->toHaveCount(3);
});

it('rebuilds only its own records when run again and keeps the password', function (): void {
    $admin = UserModel::factory()->role(UserRole::Admin)->create(['email' => 'caio@transportadora.com']);
    $ownTruck = VehicleModel::factory()->create(['plate' => 'XYZ1A23']);
    $ownLog = FuelLogModel::factory()->create(['vehicle_id' => $ownTruck->id, 'recorded_by_user_id' => $admin->id]);

    seedDemo(['--password-stdin' => true], DEMO_PASSWORD.PHP_EOL);
    [$code, $output] = seedDemo(['--force' => true]);

    expect($code)->toBe(0)
        ->and($output)->toContain('Conta de demonstração atualizada')
        ->and($output)->toContain('Senha mantida.')
        ->and(Hash::check(DEMO_PASSWORD, UserModel::query()->where('email', 'demo@frotacarga.com.br')->sole()->password))->toBeTrue()
        ->and(VehicleModel::query()->count())->toBe(10)
        ->and(FuelLogModel::query()->count())->toBe(46)
        ->and(TripModel::query()->count())->toBe(6)
        ->and(FuelLogModel::query()->whereKey($ownLog->id)->exists())->toBeTrue()
        ->and($admin->fresh()?->role)->toBe(UserRole::Admin);
});

it('never takes over an administrator account with the demo e-mail', function (): void {
    $admin = UserModel::factory()->role(UserRole::Admin)->create(['email' => 'demo@frotacarga.com.br']);

    [$code, $output] = seedDemo(['--generate-password' => true]);

    expect($code)->toBe(1)
        ->and($output)->toContain('é de Administração e não será alterada')
        ->and($admin->fresh()?->role)->toBe(UserRole::Admin)
        ->and(Hash::check('Password@123', (string) $admin->fresh()?->password))->toBeTrue()
        ->and(VehicleModel::query()->count())->toBe(0);
});

it('asks for a password before creating the account', function (): void {
    [$code, $output] = seedDemo();

    expect($code)->toBe(1)
        ->and($output)->toContain('use --generate-password ou --password-stdin')
        ->and(UserModel::query()->count())->toBe(0);
});

it('rejects a weak password', function (): void {
    [$code, $output] = seedDemo(['--password-stdin' => true], 'curta'.PHP_EOL);

    expect($code)->toBe(1)
        ->and($output)->toContain('senha')
        ->and(UserModel::query()->count())->toBe(0);
});

it('does not write the demo fleet in production without --force', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    [$code] = seedDemo(['--generate-password' => true]);

    expect($code)->toBe(1)
        ->and(UserModel::query()->count())->toBe(0)
        ->and(VehicleModel::query()->count())->toBe(0);
});
