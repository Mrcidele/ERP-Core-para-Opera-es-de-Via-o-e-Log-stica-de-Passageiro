<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Dashboard;

use App\Domain\Dashboard\Actions\GetDashboardSummaryAction;
use App\Domain\Dashboard\DTOs\DashboardScopeDTO;
use App\Domain\Identity\Enums\Permission;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Resources\Dashboard\DashboardSummaryResource;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function summary(Request $request, GetDashboardSummaryAction $action): DashboardSummaryResource
    {
        // O painel é de todos, mas cada bloco só sai para quem pode consultar o módulo.
        $can = fn (Permission $permission): bool => $request->user()?->can($permission->value) === true;

        return new DashboardSummaryResource($action->execute(new DashboardScopeDTO(
            fleet: $can(Permission::FleetView),
            operations: $can(Permission::OperationsView),
            crew: $can(Permission::CrewView),
            maintenance: $can(Permission::MaintenanceView),
        )));
    }
}
