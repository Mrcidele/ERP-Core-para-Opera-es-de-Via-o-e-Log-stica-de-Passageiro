<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

/** Categoria do veículo na frota de carga. */
enum VehicleType: string
{
    case Utility = 'utilitario';
    case Vuc = 'vuc';
    case Toco = 'toco';
    case Truck = 'truck';
    case Bitruck = 'bitruck';
    case TractorUnit = 'cavalo_mecanico';

    public function label(): string
    {
        return match ($this) {
            self::Utility => 'Utilitário / Van',
            self::Vuc => 'VUC / 3/4',
            self::Toco => 'Toco',
            self::Truck => 'Truck',
            self::Bitruck => 'Bitruck',
            self::TractorUnit => 'Cavalo mecânico',
        };
    }

    /**
     * Rendimento mínimo esperado, em metros por litro (2.000 = 2,0 km/L), quando o veículo não tem meta própria.
     * Abaixo dele, o abastecimento gera alerta de consumo alto.
     */
    public function defaultMinMetersPerLiter(): int
    {
        return match ($this) {
            self::Utility => 8_000,
            self::Vuc => 5_000,
            self::Toco => 4_500,
            self::Truck => 3_500,
            self::Bitruck => 2_500,
            self::TractorUnit => 2_000,
        };
    }
}
