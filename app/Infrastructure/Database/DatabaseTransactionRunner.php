<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Shared\Contracts\TransactionRunner;
use Closure;
use Illuminate\Support\Facades\DB;

final class DatabaseTransactionRunner implements TransactionRunner
{
    public function run(Closure $work): mixed
    {
        return DB::transaction($work);
    }
}
