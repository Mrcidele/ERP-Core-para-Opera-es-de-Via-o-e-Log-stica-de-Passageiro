<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documentação da API (/docs/api): aberta em `local` e `testing`; nos demais ambientes, só com
 * SCRAMBLE_ENABLED=true. Desligada, responde 404, como se a rota não existisse.
 * A documentação não dá acesso a dados: os endpoints continuam exigindo token e permissão.
 */
final class ApiDocsAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enabled()) {
            abort(404);
        }

        return $next($request);
    }

    public static function enabled(): bool
    {
        return app()->environment(['local', 'testing']) || config('scramble.enabled') === true;
    }
}
