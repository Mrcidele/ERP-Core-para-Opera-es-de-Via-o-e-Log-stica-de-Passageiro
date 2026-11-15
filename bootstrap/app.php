<?php

declare(strict_types=1);

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Exceptions\NotFoundException;
use App\Presentation\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // A API nunca redireciona quem não está autenticado: responde 401. As páginas vão para o login.
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A API sempre responde em JSON, mesmo que o cliente não envie o cabeçalho Accept.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());

        // Regras de negócio violadas saem no mesmo formato dos erros de validação.
        $exceptions->render(fn (DomainException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
            'errors' => [$e->field => [$e->getMessage()]],
        ], 422));

        $exceptions->render(fn (NotFoundException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
        ], 404));

        $exceptions->render(fn (AuthenticationException $e): JsonResponse => new JsonResponse([
            'message' => 'Não autenticado. Entre no sistema para continuar.',
        ], 401));

        $exceptions->render(fn (AccessDeniedHttpException $e): JsonResponse => new JsonResponse([
            'message' => 'Você não tem permissão para executar esta ação.',
        ], 403));

        $exceptions->render(fn (ThrottleRequestsException $e): JsonResponse => new JsonResponse([
            'message' => 'Muitas tentativas. Aguarde um minuto e tente de novo.',
        ], 429, $e->getHeaders()));
    })->create();
