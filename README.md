# ERP Core para Operações de Viação e Logística de Passageiros

Laravel 11 (PHP 8.4) + Vue 3/TypeScript, organizado em domínios com Clean Architecture. Regras em [`CLAUDE.md`](CLAUDE.md), desenho em [`docs/architecture.md`](docs/architecture.md).

## Banco de dados na nuvem (Neon ou Supabase)

A aplicação usa somente PostgreSQL 16. Em produção, o banco é um PostgreSQL serverless; a conexão exige TLS (`DB_SSLMODE=require`).

1. **Crie o banco.** No [Neon](https://neon.tech) ou no [Supabase](https://supabase.com), crie um projeto com PostgreSQL 16 na região mais próxima dos usuários (para o Brasil, São Paulo).
2. **Copie os dados de conexão** no painel do provedor (botão *Connect*). Cada um oferece dois endereços:

   | | Conexão direta (migrations) | Pooler em modo transação (aplicação) |
   | --- | --- | --- |
   | Neon | host sem `-pooler`, porta `5432` | mesmo host com `-pooler` no nome, porta `5432` |
   | Supabase | *Direct connection* ou *Session pooler*, porta `5432` | *Transaction pooler*, porta `6543` |

3. **Preencha o `.env`** com o endereço do pooler:

   ```dotenv
   DB_CONNECTION=pgsql
   DB_HOST=<host do pooler>
   DB_PORT=<5432 no Neon, 6543 no Supabase>
   DB_DATABASE=<banco>
   DB_USERNAME=<usuário>
   DB_PASSWORD=<senha>
   DB_SSLMODE=require
   DB_EMULATE_PREPARES=true
   ```

   `DB_EMULATE_PREPARES=true` é obrigatório no pooler em modo transação, que não mantém *prepared statements* entre consultas. Na conexão direta, use `false`. Em vez dos campos separados, pode-se colar a string completa em `DB_URL`.
4. **Rode as migrations pela conexão direta**, uma vez por deploy. A primeira cria a extensão `btree_gist`, disponível nos dois provedores:

   ```bash
   DB_HOST=<host direto> DB_PORT=5432 DB_EMULATE_PREPARES=false php artisan migrate --force
   ```

5. **Confira**: `curl https://<sua-aplicação>/api/v1/health` deve responder `200` com `"database":"up"`. Se responder `503`, o motivo está no log da aplicação.

Não rode `php artisan db:seed` em produção: o seeder cria frota, funcionários e viagens de demonstração (os usuários de teste com senha conhecida só são criados fora de produção).

## Documentação da API

A documentação OpenAPI é gerada automaticamente pelo [Scramble](https://scramble.dedoc.co) a partir das rotas, dos FormRequests e dos API Resources:

- `/docs/api`: documentação interativa, com "Try it" (autentique em `POST /api/v1/auth/login` e use o token como Bearer).
- `/docs/api.json`: o documento OpenAPI 3.1, para gerar clientes ou importar no Postman/Insomnia.

Fica aberta em `APP_ENV=local` e `testing`. Em produção e homologação responde 404, a menos que `SCRAMBLE_ENABLED=true`. A documentação não dá acesso a dados: os endpoints continuam exigindo token e permissão. A página carrega o visualizador (Stoplight Elements) de `unpkg.com`, então o navegador precisa alcançar esse CDN.

## Produção (Docker)

`docker/production/Dockerfile` gera uma imagem única com Nginx e PHP-FPM 8.4 (Alpine) sob o Supervisor, com os assets do Vue já compilados.

```bash
docker build -f docker/production/Dockerfile -t erp-viacao .
docker run -d -p 8080:8080 --env-file .env.production -e RUN_MIGRATIONS=true erp-viacao
```

Variáveis mínimas do `.env.production`: `APP_KEY` (gere com `php artisan key:generate --show`), `APP_URL`, os `DB_*` da seção anterior e, atrás de balanceador, `TRUSTED_PROXIES` (IPs do proxy, ou `*` se só ele alcança o contêiner) para que o limite de tentativas e a auditoria vejam o IP real do usuário. A imagem já define `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `DB_SSLMODE=require` e cache e sessão no banco (`CACHE_STORE=database`); para usar Redis, informe `CACHE_STORE=redis` e `REDIS_HOST`.

Na subida, o contêiner executa `config:cache`, `route:cache` e `view:cache`; com `RUN_MIGRATIONS=true` também aplica as migrations (com várias réplicas, deixe isso para uma etapa do deploy). O balanceador deve monitorar `GET /api/v1/health` na porta `8080`: `200` quando a aplicação e o banco respondem, `503` quando o banco não responde.

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

O GitHub Actions (`.github/workflows/ci.yml`) roda a mesma sequência a cada push e pull request para `main`, com um PostgreSQL 16 de serviço: Pint, Larastan, migrations, Pest, type-check, Vitest e build. Em seguida, o job `docker-smoke-test` constrói a imagem de produção, sobe o contêiner contra um PostgreSQL, aplica as migrations e confere o health check, a API e os assets.
