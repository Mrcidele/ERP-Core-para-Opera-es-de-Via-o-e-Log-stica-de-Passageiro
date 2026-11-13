<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

/**
 * Violação de regra de negócio. O campo indica a qual atributo de entrada
 * o erro se refere, para a camada HTTP devolver no formato de validação.
 */
abstract class DomainException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
