<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleClass: string
{
    case Leito = 'leito';
    case SemiLeito = 'semi_leito';
    case Executivo = 'executivo';
}
