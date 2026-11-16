#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY não definida. Gere uma com 'php artisan key:generate --show' e informe-a no ambiente." >&2
    exit 1
fi

# As variáveis de ambiente só existem na execução: os caches são montados aqui, não no build.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Opcional: aplica as migrations na subida. Com várias réplicas, deixe só uma fazer isso
# (ou rode `php artisan migrate --force` como etapa do deploy).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
