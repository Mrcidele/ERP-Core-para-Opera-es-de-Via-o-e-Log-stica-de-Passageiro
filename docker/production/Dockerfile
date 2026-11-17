# syntax=docker/dockerfile:1

# Imagem de produção do ERP Viação. Construir a partir da raiz do repositório:
#   docker build -f docker/production/Dockerfile -t erp-viacao .

# ---------- 1. Assets do frontend (Vue 3 + Vite) ----------
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY tsconfig.json vite.config.ts tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# ---------- 2. Dependências PHP, sem pacotes de desenvolvimento ----------
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
# As extensões são conferidas na imagem final; aqui só se baixam os pacotes.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# ---------- 3. Aplicação: PHP-FPM + Nginx sob o Supervisor ----------
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache nginx supervisor libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql opcache pcntl \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-app.ini
# O nome começa com zzz para ser lido depois do zz-docker.conf da imagem base, que também define `listen`.
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

# Padrões de produção; tudo pode ser sobrescrito pelas variáveis do ambiente de execução.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    DB_SSLMODE=require \
    CACHE_STORE=database \
    SESSION_DRIVER=database \
    QUEUE_CONNECTION=sync

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/api/v1/health || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
