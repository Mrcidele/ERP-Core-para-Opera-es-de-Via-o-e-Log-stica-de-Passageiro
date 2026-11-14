<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/** Publica eventos de domínio para os outros módulos reagirem, sem que um conheça o outro. */
interface EventDispatcher
{
    public function dispatch(object $event): void;
}
