<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Shared\Contracts\DatabaseProbe;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ConnectionDatabaseProbe implements DatabaseProbe
{
    public function isReachable(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable $exception) {
            // O motivo fica no log; a resposta pública não expõe detalhes da conexão.
            report($exception);

            return false;
        }
    }
}
