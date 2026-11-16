<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Atrás de um balanceador, o IP e o esquema (https) reais chegam nos cabeçalhos X-Forwarded-*.
 * Eles só são aceitos dos proxies listados em TRUSTED_PROXIES; sem a lista, são ignorados,
 * para que um cliente não possa forjar o próprio IP no limite de tentativas e na auditoria.
 */
final class TrustedProxies
{
    /**
     * @param  string|null  $setting  IPs ou faixas CIDR separados por vírgula, ou "*" para confiar em qualquer proxy
     */
    public static function apply(?string $setting): void
    {
        $proxies = array_values(array_filter(array_map(trim(...), explode(',', (string) $setting)), fn (string $proxy): bool => $proxy !== ''));

        if ($proxies === []) {
            TrustProxies::flushState();

            return;
        }

        TrustProxies::at(in_array('*', $proxies, true) ? '*' : $proxies);
    }
}
