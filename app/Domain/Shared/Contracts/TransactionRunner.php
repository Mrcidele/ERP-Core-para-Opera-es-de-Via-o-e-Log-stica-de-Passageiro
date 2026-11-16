<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use Closure;

/** Executa um caso de uso em uma transação do banco: ou tudo é gravado, ou nada. */
interface TransactionRunner
{
    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function run(Closure $work): mixed;
}
