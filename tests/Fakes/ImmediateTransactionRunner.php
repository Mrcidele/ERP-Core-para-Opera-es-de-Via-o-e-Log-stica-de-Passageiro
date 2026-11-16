<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Shared\Contracts\TransactionRunner;
use Closure;

/** Sem banco não há o que desfazer: só executa o trabalho e conta quantas transações foram abertas. */
final class ImmediateTransactionRunner implements TransactionRunner
{
    public int $transactions = 0;

    public bool $active = false;

    public function run(Closure $work): mixed
    {
        $this->transactions++;
        $this->active = true;

        try {
            return $work();
        } finally {
            $this->active = false;
        }
    }
}
