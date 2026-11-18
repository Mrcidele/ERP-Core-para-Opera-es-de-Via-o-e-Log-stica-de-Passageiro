// Espelha App\Domain\Fleet\Enums e App\Presentation\Http\Resources\Fleet\VehicleResource.

export const VEHICLE_TYPES = ['utilitario', 'vuc', 'toco', 'truck', 'bitruck', 'cavalo_mecanico'] as const;
export type VehicleType = (typeof VEHICLE_TYPES)[number];

export const VEHICLE_STATUSES = ['active', 'maintenance', 'inactive'] as const;
export type VehicleStatus = (typeof VEHICLE_STATUSES)[number];

export interface Vehicle {
    id: string;
    plate: string;
    chassis: string;
    /** Marca e modelo, ex.: "Volvo FH 540". */
    model: string | null;
    /** Carga útil em quilos. */
    payload_kg: number;
    vehicle_type: VehicleType;
    vehicle_type_label: string;
    status: VehicleStatus;
    current_mileage: number;
    /** Quilometragem da próxima revisão preventiva; null se não programada. */
    next_preventive_maintenance_km: number | null;
    /** Calculado pela API: o hodômetro já alcançou a quilometragem da revisão. */
    needs_preventive_maintenance: boolean;
    /** Meta de rendimento própria, em metros por litro (2200 = 2,2 km/L); null segue o padrão do tipo. */
    min_meters_per_liter: number | null;
    /** Meta que vale de fato: a própria ou a do tipo. */
    effective_min_meters_per_liter: number;
}

export const VEHICLE_TYPE_LABELS: Record<VehicleType, string> = {
    utilitario: 'Utilitário / Van',
    vuc: 'VUC / 3/4',
    toco: 'Toco',
    truck: 'Truck',
    bitruck: 'Bitruck',
    cavalo_mecanico: 'Cavalo mecânico',
};

/** Rendimento mínimo esperado por tipo, em metros por litro (VehicleType::defaultMinMetersPerLiter). */
export const DEFAULT_MIN_METERS_PER_LITER: Record<VehicleType, number> = {
    utilitario: 8_000,
    vuc: 5_000,
    toco: 4_500,
    truck: 3_500,
    bitruck: 2_500,
    cavalo_mecanico: 2_000,
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
