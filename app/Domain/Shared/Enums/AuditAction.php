<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/** Ações gravadas na trilha de auditoria: o código técnico e o nome que o usuário lê. */
enum AuditAction: string
{
    case VehicleDeleted = 'vehicle.deleted';
    case LineCreated = 'line.created';
    case LineUpdated = 'line.updated';
    case LineDeleted = 'line.deleted';
    case TripScheduled = 'trip.scheduled';
    case TripCompleted = 'trip.completed';
    case TripCanceled = 'trip.canceled';
    case MaintenanceOrderOpened = 'maintenance_order.opened';
    case MaintenanceOrderCompleted = 'maintenance_order.completed';
    case FuelLogRecorded = 'fuel_log.recorded';
    case FuelLogDeleted = 'fuel_log.deleted';
    case LoginFailed = 'auth.login_failed';

    public function label(): string
    {
        return match ($this) {
            self::VehicleDeleted => 'Veículo excluído',
            self::LineCreated => 'Linha criada',
            self::LineUpdated => 'Linha alterada',
            self::LineDeleted => 'Linha excluída',
            self::TripScheduled => 'Viagem agendada',
            self::TripCompleted => 'Viagem concluída',
            self::TripCanceled => 'Viagem cancelada',
            self::MaintenanceOrderOpened => 'Ordem de serviço aberta',
            self::MaintenanceOrderCompleted => 'Ordem de serviço concluída',
            self::FuelLogRecorded => 'Abastecimento registrado',
            self::FuelLogDeleted => 'Abastecimento excluído',
            self::LoginFailed => 'Login recusado',
        };
    }

    /** Nome da ação para um código gravado; um código que o sistema não conhece mais aparece como está. */
    public static function labelFor(string $code): string
    {
        return self::tryFrom($code)?->label() ?? $code;
    }

    /**
     * Códigos das ações cujo nome em português contém o termo, sem diferenciar maiúsculas nem acentos
     * ("exclu" e "excluído" encontram as exclusões).
     *
     * @return list<string>
     */
    public static function codesMatchingLabel(string $term): array
    {
        $needle = self::normalize($term);

        if ($needle === '') {
            return [];
        }

        return array_values(array_map(
            fn (self $action): string => $action->value,
            array_filter(self::cases(), fn (self $action): bool => str_contains(self::normalize($action->label()), $needle)),
        ));
    }

    private static function normalize(string $text): string
    {
        return strtr(mb_strtolower(trim($text)), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }
}
