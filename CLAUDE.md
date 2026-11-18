# Regras de arquitetura — ERP Viação

Estas regras valem para todo código gerado neste repositório. Detalhes de módulos e modelagem: `docs/architecture.md`.

## Backend (Laravel 12, PHP 8.4)

- `declare(strict_types=1);` em todos os arquivos PHP.
- Clean Architecture em três camadas, dependências sempre apontando para o domínio:
  - `app/Domain/<Módulo>/` — `Entities`, `DTOs`, `Enums`, `Exceptions`, `Repositories` (interfaces) e `Actions` (casos de uso). Sem Eloquent, Request ou facades.
  - `app/Infrastructure/` — models Eloquent (`Database/Models`), implementações de repositório (`Database/Repositories`) e `Providers`.
  - `app/Presentation/Http/` — `Controllers`, `Requests` e `Resources`.
- Controllers anêmicos: recebem o FormRequest, convertem para DTO (`$request->toDTO()`) e despacham para uma Action. Nenhuma regra de negócio.
- DTOs e entidades são `final readonly class`.
- FormRequest valida formato; regra de negócio (unicidade, estados, limites) fica na Action e falha com uma `DomainException`, que a camada HTTP devolve como 422 no formato de validação.
- Saída sempre por API Resource; coleções tipadas (`@return list<...>` / `Collection<int, ...>`).
- Classes `final` por padrão. Toda interface de repositório é ligada em `RepositoryServiceProvider`.
- Migrations com chave primária UUID e chaves estrangeiras com integridade referencial.
- Tabelas de cadastro usam soft delete; unicidade por índice único parcial em SQL (`WHERE deleted_at IS NULL`), nunca `->unique()`.
- Regra que depende da data atual recebe `App\Domain\Shared\Contracts\Clock`; nos testes, `Tests\Fakes\FixedClock`.
- Um módulo não usa classes de domínio de outro: declara em `Domain/<Módulo>/Contracts` o que precisa saber (ex.: `FleetGateway`, `CrewGateway`) e a implementação fica em `Infrastructure/Database/Gateways`. Listagens com dados de outro módulo usam um `...SummaryDTO` montado pelo repositório. Visão de leitura que cruza vários módulos (painel, grade de escala) é um contrato `...Reader` no módulo que a exibe, implementado em `Infrastructure/Database/Gateways`.
- Datas com hora entram e saem da API em ISO 8601 com fuso e são gravadas em UTC.
- Quantidades com fração trafegam e são gravadas como inteiros na menor unidade: dinheiro em centavos (`..._cents`) e volume em mililitros (`..._milliliters`). A conversão para reais e litros é da tela (`core/utils/money.ts`, `formatCents`, `formatLiters`).
- Banco: somente PostgreSQL 16 (Supabase/Neon em produção, configurado por `DB_SSLMODE` e `DB_EMULATE_PREPARES`), inclusive nos testes (banco dedicado `erp_test`). Migrations usam recursos nativos (timestamptz, CHECK, EXCLUDE, índices parciais) sem condicional de driver.
- Regra crítica de integridade tem também uma constraint no banco; o repositório converte a violação na `DomainException` correspondente.
- Caso de uso que precisa de atomicidade ou de trava recebe o contrato `TransactionRunner` (`Domain/Shared/Contracts`) e trava o registro por um método `lock...` do repositório (`SELECT ... FOR UPDATE`); a Action não usa `DB::` nem Eloquent. Nos testes, `Tests\Fakes\ImmediateTransactionRunner`.
- Módulos reagem uns aos outros por eventos em `Domain/Shared/Events`, publicados pelo contrato `EventDispatcher`; os listeners são registrados em `AppServiceProvider`.
- Rotas de API em `routes/api.php`, prefixo `/api/v1/<módulo>`. Toda rota, menos o login e o `GET /api/v1/health`, fica atrás de `auth:sanctum` e declara com `->can('<permissão>')` a permissão que a libera.
- Listagem que cresce é paginada no servidor: o FormRequest estende `ListRequest` (`page`, `per_page` até 100, `search`, `status`), a Action recebe `PageQuery` e devolve `Page`, o repositório usa o trait `PaginatesQueries` e o controller responde com `$this->paginated(...)` (`meta.current_page`, `last_page`, `per_page`, `total`). Listas curtas usadas como opções de formulário (linhas, mecânicos) vêm inteiras.
- Exportação CSV sai em streaming por `CsvStream::download(...)`, com o repositório entregando os registros por `lazy()`; datas no fuso `APP_REPORT_TIMEZONE`.
- A documentação OpenAPI é gerada pelo Scramble a partir das rotas, FormRequests e Resources (`/docs/api`, `/docs/api.json`): mantenha `rules()` e os `@return array{...}` dos Resources fiéis ao que a API aceita e devolve. Aberta em `local`/`testing`; nos demais ambientes, só com `SCRAMBLE_ENABLED=true` (middleware `ApiDocsAccess`).
- Permissões e papéis são os enums `Permission` e `UserRole` de `Domain/Identity`; a matriz de acesso vive só em `UserRole::permissions()`. Permissão nova entra no enum, na matriz e no teste `AuthorizationTest`.
- Operação crítica publica um evento em `Domain/Shared/Events`; o listener `RecordAuditTrail` grava em `audit_logs`. Toda ação auditada é um caso do enum `Domain/Shared/Enums/AuditAction`, com o nome em português em `label()`; a API devolve `action_label` e a busca da auditoria aceita esse nome. Senhas e tokens nunca entram em log nem em auditoria. `audit_logs` é somente inserção: um trigger no banco recusa UPDATE, DELETE e TRUNCATE.

## Frontend (Vue 3 + TypeScript, em `resources/js`)

- Composition API com `<script setup lang="ts">`; TypeScript em modo `strict`, sem `any`.
- Um diretório por módulo em `modules/<módulo>/`: `components`, `composables`, `pages`, `stores`, `schemas`, `types`. Rotas em `core/router`.
- `types/` espelha os API Resources e Enums do backend; `schemas/` (Zod) espelha as regras dos FormRequests.
- Estado de requisição em stores Pinia isoladas por módulo; HTTP somente via `core/api/http.ts`.
- Erros 422 da API são exibidos por campo no formulário.
- Sessão: `authStore` guarda token, usuário e permissões (vindas da API, nunca recalculadas no frontend). `core/session/installSession.ts` liga a sessão ao Axios (token, 401, 403) e à guarda do router. Rota interna declara `meta.permission`; botão de ação só aparece com `authStore.can(...)`. Esconder é conveniência: quem barra é a API.
- Design system em `resources/js/core/ui`: `AppShell`, `StatCard`, `StatusBadge` (com `statusCatalog.ts`), `MercosulPlate`, `SlideOver`, `ConfirmDialog`, `ToastContainer` + `useToast`, `EmptyState`, `TableSkeleton`, `AppIcon`, `ListToolbar` (filtro por status + `SearchField`) e `PaginationBar`. Tela nova usa esses componentes; não se recria tabela, pílula de status, placa ou painel lateral à mão.
- Tokens em `tailwind.config.js` (`ink` = azul-asfalto/slate com `ink-950` na barra lateral, `signal` = âmbar, `road` = verde, `brake` = vermelho) e classes em `resources/css/app.css` (`card`, `btn`, `field-*`, `data-table`, `segmented`, `drawer-actions`, `num`).
- Placas, chassis, horários, quilometragens e valores em R$ usam a classe `num` (fonte mono com algarismos tabulares).
- Formulários abrem em `SlideOver` com as ações em `drawer-actions`; exclusões e cancelamentos pedem confirmação em `ConfirmDialog`; confirmações de sucesso saem por `useToast().success(...)`. 403, 409 e 422 da API viram toast automaticamente (não repetir na tela).
- Toda lista trata carregando (`TableSkeleton`), vazio e erro (`EmptyState`).
- Lista paginada: a store usa `usePagedList(url)` de `core/api/pagination.ts` (busca e status vão ao servidor; depois de gravar, recarrega a página) e a tela usa `ListToolbar` + `PaginationBar`. Opções de formulário que vêm de listagem paginada usam `fetchEveryPage`. Download autenticado usa `downloadFile` de `core/api/download.ts`.
- Telas ficam em `modules/<módulo>/pages/<Nome>View.vue`.

## Qualidade

- Pest: teste de unidade para cada Action (com repositório em memória de `tests/Fakes`) e Feature test para cada endpoint.
- Larastan nível 9 e Pint sem pendências.
- Vitest + Vue Test Utils para componentes, com a API mockada. Testes de tela entram com `signInAs(papel)` de `resources/js/tests/auth.ts`.
- Testes de feature dos módulos rodam como administrador (`tests/Pest.php`); autenticação, permissões e auditoria têm testes próprios em `tests/Feature/Security`.
- Antes de concluir uma tarefa: `composer test && composer analyse && composer lint && npm run type-check && npm test`.

## Fluxo de trabalho

- Uma fatia vertical por vez (uma entidade, um caso de uso), do banco ao componente, com testes.
- Não trocar de stack nem adicionar dependências sem pedido explícito.
