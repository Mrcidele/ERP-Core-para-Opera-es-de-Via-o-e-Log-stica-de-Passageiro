# Arquitetura

## Módulos (domínios)

| Módulo | Responsabilidade |
| --- | --- |
| **Fleet** (Frota) | Cadastro de ônibus, status (ativo, manutenção, inativo), quilometragem, classe (leito, semi-leito, executivo). |
| **Operations** (Operação) | Linhas, trechos, alocação de ônibus em viagens, controle de catraca/lotação. |
| **Crew** (Tripulação) | Motoristas, jornada de trabalho e descanso, exames toxicológicos, CNH. |
| **Maintenance** (Manutenção) | Ordens de serviço, estoque de peças (pneus, óleo), preventivas e corretivas. |

## Modelagem (PostgreSQL 16)

- `vehicles`: `id`, `plate`, `chassis`, `capacity`, `class_type`, `status`, `current_mileage`
- `employees`: `id`, `name`, `cpf`, `role` (driver, mechanic, admin), `cnh_number`, `cnh_category`, `cnh_expiration_date`, `status` (active, on_leave, inactive)
- `lines`: `id`, `origin`, `destination`, `distance_km`, `estimated_duration_minutes`
- `trips`: `id`, `line_id`, `vehicle_id`, `driver_id`, `departure_time`, `arrival_time` (UTC), `status` (scheduled, in_progress, completed, canceled)
- `maintenance_orders`: `id`, `vehicle_id`, `mechanic_id`, `type` (preventive, corrective), `status` (open, in_progress, completed, canceled), `description`, `cost_cents`, `opened_at`, `completed_at`

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
| Operations | Agendamento de viagem (`POST /api/v1/operations/trips`): veículo ativo, motorista ativo com CNH válida até a chegada, sem conflito de horário | Implementado |
| Operations | Trava de concorrência no banco (`EXCLUDE USING GIST`) contra viagens sobrepostas do mesmo veículo ou motorista | Implementado |
| Operations | Listagem (`GET /operations/trips`) e mudança de status (`POST /operations/trips/{id}/start`, `/complete`, `/cancel`) com tela em tabela | Implementado |
| Operations | Evento `TripCompleted`: ao concluir a viagem, a Frota soma a distância da linha à quilometragem do veículo | Implementado |
| Operations | Listagem de linhas (`GET /api/v1/operations/lines`); as linhas vêm do seeder | Implementado |
| Operations | Cadastro de linhas, controle de lotação | Pendente |
| Maintenance | Ordens de serviço (`GET`/`POST /api/v1/maintenance/orders`, `PATCH /maintenance/orders/{id}` para concluir ou cancelar) com tela em tabela | Implementado |
| Maintenance | Abrir a ordem põe o veículo em manutenção; encerrar a última pendente devolve o veículo a ativo | Implementado |
| Maintenance | Transição para "em andamento", histórico por veículo, estoque de peças | Pendente |
| — | Autenticação/autorização da API | Pendente |
