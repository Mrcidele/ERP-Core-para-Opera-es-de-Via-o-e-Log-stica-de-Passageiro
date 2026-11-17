<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Atrás de um balanceador, o IP e o esquema (https) reais chegam nos cabeçalhos X-Forwarded-*.
 * Eles só são aceitos dos proxies listados em TRUSTED_PROXIES; sem a lista, são ignorados,
 * para que um cliente não possa forjar o próprio IP no limite de tentativas e na auditoria.
 *
 * O IP do cliente é o primeiro endereço não confiável lido da direita para a esquerda no
 * X-Forwarded-For. Por isso a lista precisa conter todos os saltos da infraestrutura:
 *
 * - `render`: o caminho do Render é cliente → Cloudflare → proxy interno do Render → contêiner.
 *   O Render só acrescenta endereços ao X-Forwarded-For (não descarta o que o cliente mandou),
 *   então a palavra confia na rede privada do Render e nas faixas publicadas da Cloudflare; o
 *   endereço à esquerda delas é o cliente, e o que ele tiver forjado mais à esquerda é ignorado.
 * - `*`: confia só no vizinho imediato (REMOTE_ADDR); serve quando há um único proxy na frente.
 * - IPs ou faixas CIDR separados por vírgula, combináveis com `render`.
 */
final class TrustedProxies
{
    /** Rede privada onde fica o proxy interno do Render. */
    private const PRIVATE_NETWORKS = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1', '::1', 'fc00::/7'];

    /** Faixas da Cloudflare (https://www.cloudflare.com/ips/), que fica na frente de todo serviço do Render. */
    private const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /** Cabeçalhos aceitos dos proxies confiáveis; os da AWS (X-Forwarded-AWS-ELB) e o `Forwarded` ficam de fora. */
    public const HEADERS = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    /**
     * @param  string|null  $setting  `render`, `*` ou IPs/faixas CIDR separados por vírgula
     */
    public static function apply(?string $setting): void
    {
        $proxies = self::resolve($setting);

        if ($proxies === []) {
            TrustProxies::flushState();

            return;
        }

        TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
        TrustProxies::withHeaders(self::HEADERS);
    }

    /**
     * @return list<string>
     */
    public static function resolve(?string $setting): array
    {
        $entries = array_values(array_filter(array_map(trim(...), explode(',', (string) $setting)), fn (string $entry): bool => $entry !== ''));

        if (in_array('*', $entries, true)) {
            return ['*'];
        }

        $proxies = [];

        foreach ($entries as $entry) {
            array_push($proxies, ...(strtolower($entry) === 'render' ? [...self::PRIVATE_NETWORKS, ...self::CLOUDFLARE] : [$entry]));
        }

        return array_values(array_unique($proxies));
    }
}
