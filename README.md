# ERP FrotaCarga — gestão operacional para transportadoras e frotas de caminhões

Controle operacional da transportadora: frota (VUC, toco, truck, bitruck, cavalo mecânico e utilitários), motoristas com CNH e descanso em dia, rotas e escala de viagens, oficina com OS que bloqueia o caminhão na escala, abastecimento com rendimento por trecho e alerta abaixo da meta de km/L de cada veículo, e trilha de auditoria que não pode ser apagada. Complementa o emissor fiscal (CT-e, MDF-e, CIOT), que fica fora do sistema.

Laravel 12 (PHP 8.4) + Vue 3/TypeScript, organizado em domínios com Clean Architecture. Regras em [`CLAUDE.md`](CLAUDE.md), desenho em [`docs/architecture.md`](docs/architecture.md).

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
   TRUSTED_PROXIES=render
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

   `PORT=8080` faz o Render encaminhar o tráfego para a porta em que o Nginx da imagem escuta (o padrão do Render é outra). `RUN_MIGRATIONS=true` aplica migrations novas a cada deploy; com uma única instância, é seguro. `TRUSTED_PROXIES=render` confia na rede interna do Render e nas faixas da Cloudflare, que fica na frente de todo serviço do Render: o proxy do Render não descarta o `X-Forwarded-For` enviado pelo cliente, e com `*` o IP visto seria o da Cloudflare, não o do usuário.

   Segredos (`APP_KEY`, `DB_PASSWORD`, `DB_URL`) entram só na aba *Environment* do serviço, nunca como argumento de build: a imagem não recebe `ARG` e o `.dockerignore` deixa `.env`, chaves e bancos locais fora dela.

4. **Primeiro acesso.** O plano gratuito desliga o serviço após 15 minutos sem tráfego; a primeira requisição depois disso leva cerca de um minuto. Confira `https://<serviço>.onrender.com/api/v1/health` (`200` com `"database":"up"`; `503` indica banco inacessível, detalhe no log) e entre com o usuário criado no passo 2.

O contêiner tem 512 MB: o PHP-FPM usa `pm = ondemand` com no máximo 5 processos de até 80 MB, que sobem sob demanda e saem após 10 s ociosos; cada consulta ao banco é cancelada após 15 s (`DB_STATEMENT_TIMEOUT`). Pelo pooler do Neon (modo transação), o `SET` feito ao abrir a conexão não é garantido em todas as conexões do servidor, então fixe o mesmo teto na role pelo SQL Editor do Neon: `ALTER ROLE neondb_owner SET statement_timeout = '15s';`.

### Criar usuários

No dia a dia, o administrador cria, edita, desativa e reativa usuários na tela **Usuários** (menu Controle). O comando abaixo serve para o primeiro administrador de uma instalação nova e para automação.

```bash
php artisan erp:create-user                       # interativo; erp:create-admin é o mesmo comando
php artisan erp:create-user --name="Ana Souza" --email=ana@transportadora.com.br --role=dispatcher

# automação, sem terminal: a senha chega pela entrada padrão, nunca como argumento
printf '%s\n' "$SENHA" | php artisan erp:create-user --name="Ana Souza" --email=ana@transportadora.com.br --password-stdin --no-interaction
```

A senha nunca é aceita como argumento, porque ficaria no histórico do shell e visível em `ps`: no terminal ela é pedida duas vezes sem eco; em automação, use `--password-stdin`. A senha precisa de 12 a 128 caracteres, com maiúsculas, minúsculas e números, e é gravada com Argon2id (contas antigas em bcrypt continuam entrando e passam para Argon2id no próximo login). `--role` (padrão `admin`) aceita `admin`, `dispatcher`, `fleet_manager` e `mechanic_supervisor`. E-mail já cadastrado é recusado, e cada criação entra na auditoria como "Usuário criado" (sem a senha).

### Role restrita da aplicação (opcional)

Por padrão a aplicação conecta como `neondb_owner`, dona das tabelas. Para que ela só leia e grave dados, sem poder criar, alterar, esvaziar ou apagar tabelas nem mexer na auditoria, crie a role `erp_app` com [`docs/sql/erp_app_role.sql`](docs/sql/erp_app_role.sql) no SQL Editor do Neon (troque a senha antes de rodar). Depois, no Render:

```dotenv
DB_USERNAME=erp_app
DB_PASSWORD=<senha da erp_app>
RUN_MIGRATIONS=false
```

Com a `erp_app` a aplicação não consegue rodar migrations: antes de publicar uma versão com migration nova, rode `php artisan migrate --force` do seu terminal como `neondb_owner`, pela conexão direta (passo 2). Tabelas criadas assim já ficam acessíveis à `erp_app`.

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
docker build -f docker/production/Dockerfile -t erp-frotacarga .
docker run -d -p 8080:8080 --env-file .env.production -e RUN_MIGRATIONS=true erp-frotacarga
```

Variáveis mínimas do `.env.production`: `APP_KEY` (gere com `php artisan key:generate --show`), `APP_URL`, os `DB_*` do pooler (seção de deploy) e, atrás de balanceador, `TRUSTED_PROXIES` (`render`, IPs/faixas do proxy, ou `*` quando há um único proxy na frente) para que o limite de tentativas e a auditoria vejam o IP real do usuário. A imagem já define `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `DB_SSLMODE=require`, `DB_STATEMENT_TIMEOUT=15s` e cache no banco (`CACHE_STORE=database`); a API autentica por token, então a sessão fica em memória (`SESSION_DRIVER=array`). A imagem não traz a extensão do Redis.

O contêiner recusa subir em produção com `APP_DEBUG=true` ou sem uma `APP_KEY` de 32 bytes, e Nginx, PHP-FPM e Supervisor rodam como `www-data`, sem root. O Nginx envia HSTS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy` em toda resposta (inclusive assets e erros), a aplicação acrescenta a CSP, e arquivos ocultos ou de projeto (`.env`, `.git`, `composer.json`, `*.sqlite`, `vendor/`, `storage/`) e os métodos `TRACE`/`TRACK` são recusados.

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
