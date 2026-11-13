<?php

declare(strict_types=1);

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Exceptions\NotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Regras de negócio violadas saem no mesmo formato dos erros de validação.
        $exceptions->render(fn (DomainException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
            'errors' => [$e->field => [$e->getMessage()]],
        ], 422));

        $exceptions->render(fn (NotFoundException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
        ], 404));
    })->create();
