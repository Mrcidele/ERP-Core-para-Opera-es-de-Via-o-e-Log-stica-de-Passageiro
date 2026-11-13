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
- `employees`: `id`, `role` (driver, mechanic, admin), `name`, `cnh_number`, `cnh_expiration`, `status`
- `routes`: `id`, `origin_city_id`, `destination_city_id`, `estimated_duration`, `distance_km`
- `trips`: `id`, `route_id`, `vehicle_id`, `driver_id`, `departure_time`, `arrival_time`, `status`
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
| Fleet | CRUD de veículos (`/api/v1/vehicles`) com tela de listagem, cadastro, edição e exclusão | Implementado |
| Fleet | Paginação da listagem | Pendente |
| Crew, Operations, Maintenance | — | Pendente |
| — | Eventos de domínio entre módulos | Pendente |
| — | Autenticação/autorização da API | Pendente |
