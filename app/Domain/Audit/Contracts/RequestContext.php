<?php

declare(strict_types=1);

namespace App\Domain\Audit\Contracts;

/** Quem está fazendo a requisição atual e de onde. */
interface RequestContext
{
    public function userId(): ?string;

    public function ipAddress(): ?string;
}
