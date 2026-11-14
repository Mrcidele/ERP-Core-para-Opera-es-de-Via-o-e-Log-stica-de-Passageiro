# Regras de arquitetura — ERP Viação

Estas regras valem para todo código gerado neste repositório. Detalhes de módulos e modelagem: `docs/architecture.md`.

## Backend (Laravel 11, PHP 8.4)

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
- Um módulo não usa classes de domínio de outro: declara em `Domain/<Módulo>/Contracts` o que precisa saber (ex.: `FleetGateway`, `CrewGateway`) e a implementação fica em `Infrastructure/Database/Gateways`. Listagens com dados de outro módulo usam um `...SummaryDTO` montado pelo repositório.
- Datas com hora entram e saem da API em ISO 8601 com fuso e são gravadas em UTC.
- Banco: somente PostgreSQL 16 (Supabase/Neon em produção, configurado por `DB_SSLMODE` e `DB_EMULATE_PREPARES`), inclusive nos testes (banco dedicado `erp_test`). Migrations usam recursos nativos (timestamptz, CHECK, EXCLUDE, índices parciais) sem condicional de driver.
- Regra crítica de integridade tem também uma constraint no banco; o repositório converte a violação na `DomainException` correspondente.
- Módulos reagem uns aos outros por eventos em `Domain/Shared/Events`, publicados pelo contrato `EventDispatcher`; os listeners são registrados em `AppServiceProvider`.
- Rotas de API em `routes/api.php`, prefixo `/api/v1`.

## Frontend (Vue 3 + TypeScript, em `resources/js`)

- Composition API com `<script setup lang="ts">`; TypeScript em modo `strict`, sem `any`.
- Um diretório por módulo em `modules/<módulo>/`: `components`, `composables`, `pages`, `stores`, `schemas`, `types`. Rotas em `core/router`.
- `types/` espelha os API Resources e Enums do backend; `schemas/` (Zod) espelha as regras dos FormRequests.
- Estado de requisição em stores Pinia isoladas por módulo; HTTP somente via `core/api/http.ts`.
- Erros 422 da API são exibidos por campo no formulário.
- Visual: cores e fontes vêm dos tokens em `tailwind.config.js` (`ink`, `signal`, `road`, `brake`) e das classes de `resources/css/app.css` (`btn`, `field-*`). Formulários abrem em `SlideOver`; exclusões pedem confirmação em `ConfirmDialog`. Toda tela trata carregando, vazio e erro.

## Qualidade

- Pest: teste de unidade para cada Action (com repositório em memória de `tests/Fakes`) e Feature test para cada endpoint.
- Larastan nível 9 e Pint sem pendências.
- Vitest + Vue Test Utils para componentes, com a API mockada.
- Antes de concluir uma tarefa: `composer test && composer analyse && composer lint && npm run type-check && npm test`.

## Fluxo de trabalho

- Uma fatia vertical por vez (uma entidade, um caso de uso), do banco ao componente, com testes.
- Não trocar de stack nem adicionar dependências sem pedido explícito.
