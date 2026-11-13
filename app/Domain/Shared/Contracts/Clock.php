<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use DateTimeImmutable;

/** Fonte da data atual, para as regras de negócio não dependerem do relógio do sistema. */
interface Clock
{
    /** Data de hoje, à meia-noite. */
    public function today(): DateTimeImmutable;
}
