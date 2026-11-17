# ERP Core para Operações de Viação e Logística de Passageiros

Laravel 11 (PHP 8.4) + Vue 3/TypeScript, organizado em domínios com Clean Architecture. Regras em [`CLAUDE.md`](CLAUDE.md), desenho em [`docs/architecture.md`](docs/architecture.md).

## Deploy (Neon + Render)

A aplicação usa somente PostgreSQL 16 com TLS (`DB_SSLMODE=require`). O fluxo abaixo usa o Neon como banco e o Render como hospedagem da imagem Docker.

1. **Banco no Neon.** Crie um projeto com PostgreSQL 16 na região **AWS US East 1 (N. Virginia)**, a mesma do Render (cada consulta atravessa essa distância; em regiões diferentes, uma tela faz dezenas de idas e voltas transcontinentais). No botão *Connect*, copie os dois endereços:

   | Uso | Host | `DB_EMULATE_PREPARES` |
   | --- | --- | --- |
   | Comandos locais (migrations, seed, criação de usuário) | conexão direta, **sem** `-pooler` no nome | `false` |
   | Aplicação no Render | pooler, **com** `-pooler` no nome | `true` |

   No pooler (modo transação) os *prepared statements* não sobrevivem entre consultas, por isso `DB_EMULATE_PREPARES=true`; na conexão direta, `false`.

2. **Prepare o banco a partir da sua máquina**, pela conexão direta. O plano gratuito do Render não tem shell, então estes comandos rodam localmente. Passe as variáveis na linha de comando: variáveis reais do ambiente têm precedência sobre o `.env`, então o `.env` local continua apontando para o banco de desenvolvimento.

   ```bash
   export APP_ENV=production DB_CONNECTION=pgsql DB_HOST=<host direto> DB_PORT=5432 \
          DB_DATABASE=neondb DB_USERNAME=<usuário> DB_PASSWORD=<senha> \
          DB_SSLMODE=require DB_EMULATE_PREPARES=false

   php artisan migrate --force          # a primeira migration cria a extensão btree_gist
   php artisan erp:create-admin         # pede nome, e-mail e senha (sem eco)
   php artisan db:seed --force          # opcional: frota, funcionários e viagens de demonstração
   ```

   `APP_ENV=production` é o que impede o seeder de criar os usuários de teste com a senha pública `Password@123`. Nunca rode o seed contra o Neon com `APP_ENV=local`. Feche o terminal (ou `unset` as variáveis) ao terminar.

3. **Web service no Render.** *New → Web Service*, a partir deste repositório, runtime **Docker**, `Dockerfile Path` = `docker/production/Dockerfile`, região **Virginia (US East)**, *Health Check Path* = `/api/v1/health`. Variáveis de ambiente:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=<php artisan key:generate --show>
   APP_URL=https://<serviço>.onrender.com
   APP_REPORT_TIMEZONE=America/Sao_Paulo
   PORT=8080
   RUN_MIGRATIONS=true
   TRUSTED_PROXIES=*
   DB_CONNECTION=pgsql
   DB_HOST=<host com -pooler>
   DB_PORT=5432
   DB_DATABASE=neondb
   DB_USERNAME=<usuário>
   DB_PASSWORD=<senha>
   DB_SSLMODE=require
   DB_EMULATE_PREPARES=true
   # SCRAMBLE_ENABLED=true   # opcional: publica /docs/api
   ```

   `PORT=8080` faz o Render encaminhar o tráfego para a porta em que o Nginx da imagem escuta (o padrão do Render é outra). `RUN_MIGRATIONS=true` aplica migrations novas a cada deploy; com uma única instância, é seguro.

4. **Primeiro acesso.** O plano gratuito desliga o serviço após 15 minutos sem tráfego; a primeira requisição depois disso leva cerca de um minuto. Confira `https://<serviço>.onrender.com/api/v1/health` (`200` com `"database":"up"`; `503` indica banco inacessível, detalhe no log) e entre com o usuário criado no passo 2.

O contêiner tem 512 MB: o PHP-FPM usa `pm = ondemand` com no máximo 5 processos, que sobem sob demanda e saem após 10 s ociosos.

### Criar usuários

```bash
php artisan erp:create-user                       # interativo; erp:create-admin é o mesmo comando
php artisan erp:create-user --name="Ana Souza" --email=ana@viacao.com.br --role=dispatcher
```

Sem `--password`, a senha é pedida duas vezes sem eco; prefira assim, porque a opção fica no histórico do shell. A senha precisa de 12 a 72 caracteres, com maiúsculas, minúsculas e números. `--role` (padrão `admin`) aceita `admin`, `dispatcher`, `fleet_manager` e `mechanic_supervisor`. E-mail já cadastrado é recusado, e cada criação entra na auditoria como "Usuário criado" (sem a senha).

### Supabase

Também funciona: use o *Transaction pooler* (porta `6543`, `DB_EMULATE_PREPARES=true`) na aplicação e a *Direct connection* (porta `5432`, `false`) nos comandos locais.

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

Variáveis mínimas do `.env.production`: `APP_KEY` (gere com `php artisan key:generate --show`), `APP_URL`, os `DB_*` do pooler (seção de deploy) e, atrás de balanceador, `TRUSTED_PROXIES` (IPs do proxy, ou `*` se só ele alcança o contêiner) para que o limite de tentativas e a auditoria vejam o IP real do usuário. A imagem já define `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `DB_SSLMODE=require` e cache e sessão no banco (`CACHE_STORE=database`). A imagem não traz a extensão do Redis; cache e sessão ficam no PostgreSQL.

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

Com `npm run dev`, a tela de login mostra atalhos para esses perfis; no build de produção eles não existem. Em ambientes expostos, crie os usuários com `php artisan erp:create-user`. O token expira em `SANCTUM_TOKEN_EXPIRATION` minutos (padrão: 480).

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
