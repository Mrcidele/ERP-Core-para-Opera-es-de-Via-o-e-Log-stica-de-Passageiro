<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    private const HEADERS = [
        // O navegador não tenta adivinhar o tipo do conteúdo.
        'X-Content-Type-Options' => 'nosniff',
        // A aplicação não pode ser embutida em iframe (clickjacking).
        'X-Frame-Options' => 'DENY',
        // Para outros sites vai só a origem, nunca o caminho da página.
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
