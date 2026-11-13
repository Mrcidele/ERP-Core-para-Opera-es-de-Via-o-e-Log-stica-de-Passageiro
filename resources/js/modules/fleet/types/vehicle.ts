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
}

export const VEHICLE_CLASS_LABELS: Record<VehicleClass, string> = {
    leito: 'Leito',
    semi_leito: 'Semi-leito',
    executivo: 'Executivo',
};
