<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Contracts\DatabaseProbe;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(DatabaseProbe $database, Clock $clock): JsonResponse
    {
        $up = $database->isReachable();

        return response()->json([
            'status' => $up ? 'ok' : 'unavailable',
            'database' => $up ? 'up' : 'down',
            'timestamp' => $clock->now()->format(DATE_ATOM),
        ], $up ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
