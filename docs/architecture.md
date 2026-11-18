# Arquitetura

## Módulos (domínios)

| Módulo | Responsabilidade |
| --- | --- |
| **Fleet** (Frota) | Cadastro de ônibus, status (ativo, manutenção, inativo), quilometragem, classe (leito, semi-leito, executivo). |
| **Operations** (Operação) | Linhas, trechos, alocação de ônibus em viagens, controle de catraca/lotação. |
| **Crew** (Tripulação) | Motoristas, jornada de trabalho e descanso, exames toxicológicos, CNH. |
| **Maintenance** (Manutenção) | Ordens de serviço, estoque de peças (pneus, óleo), preventivas e corretivas. |
| **Fuel** (Abastecimento) | Abastecimentos por veículo, rendimento em km/L e alerta de consumo. |

## Modelagem (PostgreSQL 16)

- `vehicles`: `id`, `plate`, `chassis`, `capacity`, `class_type`, `status`, `current_mileage`
- `employees`: `id`, `name`, `cpf`, `role` (driver, mechanic, admin), `cnh_number`, `cnh_category`, `cnh_expiration_date`, `status` (active, on_leave, inactive)
- `lines`: `id`, `origin`, `destination`, `distance_km`, `estimated_duration_minutes`
- `line_stops`: `id`, `line_id` (cascade), `sequence` (> 0, única por linha), `city_name`, `cumulative_km`, `estimated_minutes`
- `trips`: `id`, `line_id`, `vehicle_id`, `driver_id`, `departure_time`, `arrival_time` (UTC), `status` (scheduled, in_progress, completed, canceled)
- `fuel_logs`: `id`, `vehicle_id`, `recorded_by_user_id`, `odometer_km`, `liters_milliliters`, `total_cost_cents`, `station_name`, `fueled_at` (UTC), soft delete; índice `(vehicle_id, fueled_at)`
- `maintenance_orders`: `id`, `vehicle_id`, `mechanic_id`, `type` (preventive, corrective), `status` (open, in_progress, completed, canceled), `description`, `cost_cents`, `opened_at`, `completed_at`

## Papéis e permissões

| Permissão | admin | dispatcher | fleet_manager | mechanic_supervisor |
| --- | :-: | :-: | :-: | :-: |
| `fleet.view` | ✓ | ✓ | ✓ | ✓ |
| `fleet.manage` | ✓ | | ✓ | |
| `crew.view` | ✓ | ✓ | ✓ | |
| `crew.manage` | ✓ | | | |
| `operations.view` | ✓ | ✓ | ✓ | |
| `operations.manage` | ✓ | ✓ | | |
| `maintenance.view` | ✓ | | ✓ | ✓ |
| `maintenance.manage` | ✓ | | ✓ | ✓ |
| `audit.view` | ✓ | | | |

A supervisão de oficina não tem `crew.view`; por isso o formulário de ordem de serviço busca os mecânicos em `GET /api/v1/maintenance/mechanics` (`maintenance.view`), que devolve só id e nome.

## Diretórios

```
app/
├── Domain/
│   ├── Shared/Exceptions/
│   └── Fleet/
│       ├── Entities/
│       ├── DTOs/
│       ├── Enums/
│       ├── Exceptions/
│       ├── Repositories/    # interfaces
│       └── Actions/         # casos de uso
├── Infrastructure/
│   ├── Database/
│   │   ├── Models/          # Eloquent
│   │   └── Repositories/    # implementações
│   └── Providers/
└── Presentation/
    ├── Console/Commands/    # comandos artisan (erp:create-user)
    └── Http/
        ├── Controllers/
        ├── Requests/
        └── Resources/

resources/js/
├── modules/
│   └── fleet/
│       ├── components/
│       ├── composables/
│       ├── pages/           # telas ligadas às rotas
│       ├── schemas/         # Zod
│       ├── stores/          # Pinia
│       └── types/
└── core/
    ├── api/                 # Axios e tratamento de erros
    ├── ui/                  # design system: AppShell, StatCard, StatusBadge, MercosulPlate, SlideOver, toasts…
    ├── session/             # liga a sessão ao Axios e ao router
    ├── router/              # Vue Router
    └── utils/
```

## Status

| Módulo | Fatia | Estado |
| --- | --- | --- |
| Fleet | CRUD de veículos (`/api/v1/fleet/vehicles`) com tela de listagem, cadastro, edição e exclusão lógica (soft delete) | Implementado |
| Crew | Cadastro de funcionário (`POST /api/v1/crew/employees`) com formulário em painel lateral | Implementado |
| Crew | Edição e exclusão de funcionários; jornada e escalas | Pendente |
| Crew | Listagem de funcionários (`GET /api/v1/crew/employees`), usada pelo agendamento | Implementado |
| Operations | Agendamento de viagem (`POST /api/v1/operations/trips`): veículo ativo, motorista ativo com CNH válida até a chegada, sem conflito de horário | Implementado |
| Operations | Trava de concorrência no banco (`EXCLUDE USING GIST`) contra viagens sobrepostas do mesmo veículo ou motorista | Implementado |
| Operations | Listagem (`GET /operations/trips`) e mudança de status (`POST /operations/trips/{id}/start`, `/complete`, `/cancel`) com tela em tabela | Implementado |
| Operations | Evento `TripCompleted`: ao concluir a viagem, a Frota soma a distância da linha à quilometragem do veículo | Implementado |
| Operations | Linhas com paradas intermediárias (`GET`/`POST /api/v1/operations/lines`, `PUT`/`DELETE /operations/lines/{id}`): linha e paradas gravadas em uma transação, auditoria, exclusão lógica bloqueada com viagem agendada ou em andamento; tela Linhas e rotas | Implementado |
| Operations | Controle de lotação | Pendente |
| Maintenance | Ordens de serviço (`GET`/`POST /api/v1/maintenance/orders`, `PATCH /maintenance/orders/{id}` para concluir ou cancelar) com tela em tabela | Implementado |
| Maintenance | Abrir a ordem põe o veículo em manutenção; encerrar a última pendente devolve o veículo a ativo | Implementado |
| Maintenance | Transição para "em andamento", histórico por veículo, estoque de peças | Pendente |
| Identity | Login, logout e sessão (`/api/v1/auth/login`, `/logout`, `/me`) com tokens Sanctum; usuários com UUID e papel | Implementado |
| Identity | RBAC: 4 papéis, 9 permissões, um Gate por permissão, 401/403 padronizados | Implementado |
| Identity | Criação de usuário pelo comando `erp:create-user` (alias `erp:create-admin`), com auditoria | Implementado |
| Segurança | Senhas Argon2id (bcrypt antigo migra no login), limite de login por conta e por IP, login realizado e recusado na auditoria, `audit_logs` somente inserção (trigger), CSP e cabeçalhos HTTP, contêiner sem root e com fail-fast de configuração | Implementado |
| Segurança | Limite de 5 logins/min por e-mail + IP e 120 req/min por usuário; cabeçalhos de segurança | Implementado |
| Audit | `audit_logs` (JSONB) gravado por eventos; consulta em `GET /api/v1/audit-logs` (`audit.view`) | Implementado |
| Frontend | Tela de login, sessão persistida, guarda de rotas, menus e ações por permissão | Implementado |
| Operations | Descanso de 11 horas do motorista entre viagens, na Action e em constraint do banco | Implementado |
| Fleet | Revisão preventiva por quilometragem (`next_preventive_maintenance_km`, `needs_preventive_maintenance`) | Implementado |
| Dashboard | `GET /api/v1/dashboard/summary` e painel com indicadores, distribuição da frota, alertas e próximas viagens; cada bloco só para quem pode consultar o módulo | Implementado |
| Audit | Tela de auditoria (`/audit-logs`) com painel de antes e depois | Implementado |
| Crew | Tela de listagem de funcionários com alerta de CNH | Implementado |
| Frontend | Design system em `core/ui` e redesenho de todas as telas | Implementado |
| Todos | Paginação no servidor (`page`, `per_page`, `search`, `status`, com `meta`) em veículos, funcionários, viagens, ordens de serviço e auditoria; `PaginationBar` nas telas | Implementado |
| Operations / Maintenance | Exportação CSV em streaming (`GET /operations/trips/export`, `GET /maintenance/orders/export`) com os filtros da tela | Implementado |
| Infra | `GET /api/v1/health` (200/503 conforme o banco), imagem de produção (`docker/production/Dockerfile`) e CI no GitHub Actions (`.github/workflows/ci.yml`) | Implementado |
| Fleet | Filtro `needs_maintenance` na listagem (revisão preventiva vencida), com o filtro rápido na tela | Implementado |
| Audit | Ações com nome em português (`AuditAction`, `action_label`) e busca por esse nome | Implementado |
| Operations | Agendamento e exclusão de linha em transação com a linha travada (`FOR UPDATE`): nunca há viagem ativa em linha excluída | Implementado |
| Infra | Smoke test da imagem de produção no CI: build, migrations, health 200/503, login e assets | Implementado |
| Audit | Agendamento de viagem auditado (`trip.scheduled`) | Implementado |
| Fuel | Abastecimentos (`GET`/`POST /api/v1/fuel/logs`): veículo travado, hodômetro e data nunca anteriores ao último abastecimento do veículo, hodômetro eleva a quilometragem da frota, rendimento em km/L por trecho e alerta abaixo de 2,2 km/L, auditoria `fuel_log.recorded`, gasto do mês no painel; tela Abastecimento | Implementado |
| Fuel | Exclusão lógica de abastecimento (`DELETE /api/v1/fuel/logs/{id}`, auditoria `fuel_log.deleted`): o rendimento do abastecimento seguinte é recalculado e o lançamento correto pode ser feito em seguida; resumo do mês na tela | Implementado |
| Fuel | Edição de abastecimento, relatório de consumo por veículo | Pendente |
| Operations | Grade de escala (`GET /api/v1/operations/schedule-timeline?date=`): 48 horas por veículo e por motorista, com ordens de serviço pendentes e o descanso de 11 horas; na tela, alternância Lista / Grade e agendamento a partir de um horário livre | Implementado |
| Maintenance / Operations | Previsão de término opcional na ordem de serviço (`estimated_completion_at`); na grade de escala o bloco de oficina termina nela | Implementado |
| Infra | Documentação OpenAPI automática (Scramble) em `/docs/api` | Implementado |
| Identity | Cadastro e gestão de usuários pela API/tela, troca e recuperação de senha | Pendente |
