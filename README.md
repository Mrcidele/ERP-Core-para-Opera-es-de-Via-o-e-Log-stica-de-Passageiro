# ERP Core para Operações de Viação e Logística de Passageiros

Laravel 11 (PHP 8.4) + Vue 3/TypeScript, organizado em domínios com Clean Architecture. Regras em [`CLAUDE.md`](CLAUDE.md), desenho em [`docs/architecture.md`](docs/architecture.md).

## Subindo o ambiente (Sail: PostgreSQL + Redis)

```bash
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

## Qualidade

```bash
composer test       # Pest
composer analyse    # Larastan nível 9
composer lint       # Pint
npm run type-check  # vue-tsc
npm test            # Vitest
```
