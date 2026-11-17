<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em toda resposta da aplicação. O Nginx da imagem de produção repete os
 * mesmos valores para o que ele serve sozinho (assets e erros); a CSP vem só daqui, porque depende
 * da rota.
 */
final class SecurityHeaders
{
    private const HEADERS = [
        // O navegador não tenta adivinhar o tipo do conteúdo.
        'X-Content-Type-Options' => 'nosniff',
        // A aplicação não pode ser embutida em iframe (clickjacking).
        'X-Frame-Options' => 'DENY',
        // Para outros sites vai só a origem, nunca o caminho da página.
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // Recursos do navegador que o ERP não usa ficam bloqueados para qualquer script.
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        // Outra janela aberta pelo ERP não consegue manipular esta.
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    /**
     * Política do SPA: scripts e estilos só do próprio domínio (build do Vite, sem eval nem inline),
     * fontes do Bunny Fonts e chamadas de API só para a mesma origem.
     */
    public const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        ."script-src 'self'; "
        ."style-src 'self' https://fonts.bunny.net; "
        ."font-src 'self' https://fonts.bunny.net; "
        ."img-src 'self' data:; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        ."frame-ancestors 'none'";

    public const STRICT_TRANSPORT_SECURITY = 'max-age=31536000; includeSubDomains';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        // HSTS só faz sentido (e só é aceito pelo navegador) em conexão HTTPS.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', self::STRICT_TRANSPORT_SECURITY);
        }

        if ($this->needsContentSecurityPolicy($request, $response)) {
            $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }

        return $response;
    }

    /**
     * A documentação da API (Scramble) carrega o visualizador de um CDN com script inline, e o
     * servidor do Vite em desenvolvimento injeta scripts de outra porta: os dois ficam sem CSP.
     */
    private function needsContentSecurityPolicy(Request $request, Response $response): bool
    {
        if ($request->is('docs/api', 'docs/api.json') || Vite::isRunningHot()) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type');

        return $type === '' || str_contains($type, 'text/html');
    }
}
