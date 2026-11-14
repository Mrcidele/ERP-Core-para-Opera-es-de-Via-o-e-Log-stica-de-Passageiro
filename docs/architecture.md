# Arquitetura

## Módulos (domínios)

| Módulo | Responsabilidade |
| --- | --- |
| **Fleet** (Frota) | Cadastro de ônibus, status (ativo, manutenção, inativo), quilometragem, classe (leito, semi-leito, executivo). |
| **Operations** (Operação) | Linhas, trechos, alocação de ônibus em viagens, controle de catraca/lotação. |
| **Crew** (Tripulação) | Motoristas, jornada de trabalho e descanso, exames toxicológicos, CNH. |
| **Maintenance** (Manutenção) | Ordens de serviço, estoque de peças (pneus, óleo), preventivas e corretivas. |

## Modelagem inicial (PostgreSQL)

- `vehicles`: `id`, `plate`, `chassis`, `capacity`, `class_type`, `status`, `current_mileage`
- `employees`: `id`, `name`, `cpf`, `role` (driver, mechanic, admin), `cnh_number`, `cnh_category`, `cnh_expiration_date`, `status` (active, on_leave, inactive)
- `lines`: `id`, `origin`, `destination`, `distance_km`, `estimated_duration_minutes`
- `trips`: `id`, `line_id`, `vehicle_id`, `driver_id`, `departure_time`, `arrival_time` (UTC), `status` (scheduled, in_progress, completed, canceled)
- `maintenance_orders`: `id`, `vehicle_id`, `mechanic_id`, `description`, `cost`, `started_at`, `finished_at`, `status`

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
    ├── components/          # SlideOver, ConfirmDialog, AppIcon
    ├── layout/              # AppShell (barra lateral + conteúdo)
    ├── pages/
    ├── router/              # Vue Router
    └── utils/
```

## Status

| Módulo | Fatia | Estado |
| --- | --- | --- |
| Fleet | CRUD de veículos (`/api/v1/vehicles`) com tela de listagem, cadastro, edição e exclusão lógica (soft delete) | Implementado |
| Fleet | Paginação da listagem | Pendente |
| Crew | Cadastro de funcionário (`POST /api/v1/crew/employees`) com formulário em painel lateral | Implementado |
| Crew | Listagem, edição e exclusão de funcionários; jornada e escalas | Pendente |
| Crew | Listagem de funcionários (`GET /api/v1/crew/employees`), usada pelo agendamento | Implementado |
| Operations | Agendamento de viagem (`POST /api/v1/operations/trips`) com bloqueio de conflito de horário por veículo e por motorista | Implementado |
| Operations | Listagem de linhas (`GET /api/v1/operations/lines`); as linhas vêm do seeder | Implementado |
| Operations | Cadastro de linhas, listagem e mudança de status de viagens, controle de lotação | Pendente |
| Operations | Trava contra agendamentos simultâneos (hoje a checagem de conflito é só por consulta) | Pendente |
| Maintenance | — | Pendente |
| — | Eventos de domínio entre módulos | Pendente |
| — | Autenticação/autorização da API | Pendente |
