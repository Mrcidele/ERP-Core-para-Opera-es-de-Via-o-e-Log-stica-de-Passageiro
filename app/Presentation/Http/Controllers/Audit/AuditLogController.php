<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Audit;

use App\Domain\Audit\Actions\ListAuditLogsAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Audit\ListAuditLogsRequest;
use App\Presentation\Http\Resources\Audit\AuditLogResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditLogController extends Controller
{
    public function index(ListAuditLogsRequest $request, ListAuditLogsAction $action): AnonymousResourceCollection
    {
        return $this->paginated(AuditLogResource::class, $action->execute($request->toPageQuery()));
    }
}
