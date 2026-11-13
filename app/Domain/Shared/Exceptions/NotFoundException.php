<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

/** Recurso inexistente; a camada HTTP devolve 404. */
abstract class NotFoundException extends RuntimeException {}
