<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use RuntimeException;

/**
 * Impede a aplicação de subir em produção com configuração que expõe dados: APP_DEBUG ligado
 * mostra stack traces com SQL e caminhos internos, e sem uma APP_KEY de 32 bytes os tokens e
 * cookies criptografados ficam fracos ou quebram.
 */
final class InsecureConfigurationGuard
{
    public static function check(string $environment, mixed $debug, mixed $key): void
    {
        if ($environment !== 'production') {
            return;
        }

        if ($debug === true) {
            throw new RuntimeException('APP_DEBUG=true não é permitido em produção.');
        }

        if (! self::isValidKey($key)) {
            throw new RuntimeException('APP_KEY ausente ou inválida. Gere uma com `php artisan key:generate --show`.');
        }
    }

    private static function isValidKey(mixed $key): bool
    {
        if (! is_string($key) || $key === '') {
            return false;
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return is_string($decoded) && strlen($decoded) === 32;
        }

        return strlen($key) === 32;
    }
}
