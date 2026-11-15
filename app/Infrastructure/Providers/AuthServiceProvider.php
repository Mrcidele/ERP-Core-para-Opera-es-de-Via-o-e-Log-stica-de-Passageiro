<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Audit\Contracts\RequestContext;
use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\TokenService;
use App\Domain\Identity\Enums\Permission;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Auth\LaravelRequestContext;
use App\Infrastructure\Auth\SanctumTokenService;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PasswordHasher::class => LaravelPasswordHasher::class,
        TokenService::class => SanctumTokenService::class,
        RequestContext::class => LaravelRequestContext::class,
    ];

    public function boot(): void
    {
        // Um Gate por permissão; quem decide é a matriz de UserRole::permissions().
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (UserModel $user): bool => $user->role->can($permission));
        }

        // Login: 5 tentativas por minuto para cada par e-mail + IP.
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->trim()->toString());

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // API: 120 requisições por minuto por usuário (ou por IP, sem autenticação).
        RateLimiter::for('api', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(120)->by(is_string($userId) ? $userId : (string) $request->ip());
        });
    }
}
