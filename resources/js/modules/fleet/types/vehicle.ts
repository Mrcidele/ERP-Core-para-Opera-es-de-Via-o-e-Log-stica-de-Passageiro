// Espelha App\Domain\Fleet\Enums e App\Presentation\Http\Resources\Fleet\VehicleResource.

export const VEHICLE_CLASSES = ['leito', 'semi_leito', 'executivo'] as const;
export type VehicleClass = (typeof VEHICLE_CLASSES)[number];

export const VEHICLE_STATUSES = ['active', 'maintenance', 'inactive'] as const;
export type VehicleStatus = (typeof VEHICLE_STATUSES)[number];

export interface Vehicle {
    id: string;
    plate: string;
    chassis: string;
    capacity: number;
    class_type: VehicleClass;
    status: VehicleStatus;
    current_mileage: number;
    /** Quilometragem da próxima revisão preventiva; null se não programada. */
    next_preventive_maintenance_km: number | null;
    /** Calculado pela API: o hodômetro já alcançou a quilometragem da revisão. */
    needs_preventive_maintenance: boolean;
}

export const VEHICLE_CLASS_LABELS: Record<VehicleClass, string> = {
    leito: 'Leito',
    semi_leito: 'Semi-leito',
    executivo: 'Executivo',
};

export const VEHICLE_STATUS_LABELS: Record<VehicleStatus, string> = {
    active: 'Ativo',
    maintenance: 'Em manutenção',
    inactive: 'Inativo',
};

/** Intervalo usual entre revisões preventivas, usado só para desenhar a barra de progresso. */
export const PREVENTIVE_INTERVAL_KM = 20_000;

export interface PreventiveProgress {
    /** Quilômetros até a revisão; negativo quando já passou. */
    remainingKm: number;
    /** Quanto do intervalo já foi rodado, de 0 a 100. */
    percent: number;
    due: boolean;
    /** Faltam 10% do intervalo ou menos. */
    near: boolean;
}

/** Situação da revisão preventiva de um veículo; null quando não há quilometragem programada. */
export function preventiveProgress(vehicle: Pick<Vehicle, 'current_mileage' | 'next_preventive_maintenance_km'>): PreventiveProgress | null {
    if (vehicle.next_preventive_maintenance_km === null) {
        return null;
    }

    const remainingKm = vehicle.next_preventive_maintenance_km - vehicle.current_mileage;
    const percent = Math.min(100, Math.max(0, Math.round((1 - remainingKm / PREVENTIVE_INTERVAL_KM) * 100)));

    return { remainingKm, percent, due: remainingKm <= 0, near: remainingKm > 0 && remainingKm <= PREVENTIVE_INTERVAL_KM * 0.1 };
}
