#!/bin/sh
set -eu

cd /var/www/html

fail() {
    echo "Configuração insegura: $1" >&2
    exit 1
}

# Fail-fast: produção não sobe com depuração ligada nem sem uma APP_KEY de 32 bytes.
if [ "${APP_ENV:-production}" = "production" ]; then
    case "$(printf '%s' "${APP_DEBUG:-false}" | tr '[:upper:]' '[:lower:]')" in
        true|1|on|yes) fail "APP_DEBUG ligado em produção. Defina APP_DEBUG=false." ;;
    esac
fi

if [ -z "${APP_KEY:-}" ]; then
    fail "APP_KEY não definida. Gere uma com 'php artisan key:generate --show' e informe-a no ambiente."
fi

case "$APP_KEY" in
    base64:*)
        key_bytes=$(printf '%s' "${APP_KEY#base64:}" | base64 -d 2>/dev/null | wc -c)
        [ "$key_bytes" -eq 32 ] || fail "APP_KEY inválida (precisa de 32 bytes). Gere outra com 'php artisan key:generate --show'."
        ;;
    *)
        [ "${#APP_KEY}" -eq 32 ] || fail "APP_KEY inválida (precisa de 32 bytes). Gere outra com 'php artisan key:generate --show'."
        ;;
esac

mkdir -p /tmp/nginx

# As variáveis de ambiente só existem na execução: os caches são montados aqui, não no build.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Opcional: aplica as migrations na subida. Com várias réplicas, deixe só uma fazer isso
# (ou rode `php artisan migrate --force` como etapa do deploy).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
