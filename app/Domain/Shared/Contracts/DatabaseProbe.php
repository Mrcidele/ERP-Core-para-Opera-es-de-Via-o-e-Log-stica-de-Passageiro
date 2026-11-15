<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/** Verifica se o banco de dados responde. */
interface DatabaseProbe
{
    public function isReachable(): bool;
}
