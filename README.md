# ERP Core para Operações de Viação e Logística de Passageiros

Laravel 11 (PHP 8.4) + Vue 3/TypeScript, organizado em domínios com Clean Architecture. Regras em [`CLAUDE.md`](CLAUDE.md), desenho em [`docs/architecture.md`](docs/architecture.md).

## Banco de dados (PostgreSQL serverless)

O banco é um PostgreSQL na nuvem (Supabase ou Neon). No `.env`, preencha `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` (ou `DB_URL`) com os dados do provedor e mantenha `DB_SSLMODE=require`. Ao conectar pelo pooler em modo transação (Supabase na porta 6543, Neon com `-pooler` no host), defina `DB_EMULATE_PREPARES=true`. Rode as migrations pela conexão direta.

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

## Acesso

Toda a API exige login (token Sanctum). O `php artisan db:seed` cria, fora de produção, um usuário por papel, todos com a senha `Password@123`:

| E-mail | Papel |
| --- | --- |
| `admin@erp.test` | Administração (tudo, inclusive auditoria) |
| `despachante@erp.test` | Despacho (agenda e conduz viagens) |
| `frota@erp.test` | Gestão de frota (veículos e oficina) |
| `oficina@erp.test` | Supervisão de oficina (ordens de serviço) |

Com `npm run dev`, a tela de login mostra atalhos para esses perfis; no build de produção eles não existem. Troque essas senhas ou não rode o seeder em ambientes expostos. O token expira em `SANCTUM_TOKEN_EXPIRATION` minutos (padrão: 480).

## Testes

Os testes de feature rodam somente em PostgreSQL 16, num banco dedicado. Com um PostgreSQL local (o serviço `pgsql` do `docker-compose.yml` serve), crie-o uma vez:

```sql
CREATE USER erp WITH PASSWORD 'secret' SUPERUSER;
CREATE DATABASE erp_test OWNER erp;
```

O usuário precisa poder criar a extensão `btree_gist`. Host, banco e credenciais ficam em `phpunit.xml` e `.env.testing`. Nunca aponte os testes para o banco de produção: cada execução recria as tabelas.

## Qualidade

```bash
composer test       # Pest
composer analyse    # Larastan nível 9
composer lint       # Pint
npm run type-check  # vue-tsc
npm test            # Vitest
```
