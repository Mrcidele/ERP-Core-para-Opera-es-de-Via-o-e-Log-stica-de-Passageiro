<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Operations;

use App\Domain\Operations\Actions\ListLinesAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Resources\Operations\LineResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LineController extends Controller
{
    public function index(ListLinesAction $action): AnonymousResourceCollection
    {
        return LineResource::collection($action->execute());
    }
}
