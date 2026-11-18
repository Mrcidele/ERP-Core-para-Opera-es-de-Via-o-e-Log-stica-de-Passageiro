import { EMPLOYEE_STATUS_LABELS, type EmployeeStatus } from '@/modules/crew/types/employee';
import { VEHICLE_STATUS_LABELS, type VehicleStatus } from '@/modules/fleet/types/vehicle';
import { MAINTENANCE_STATUS_LABELS, type MaintenanceStatus } from '@/modules/maintenance/types/maintenance';
import { TRIP_STATUS_LABELS, type TripStatus } from '@/modules/operations/types/trip';
import { USER_STATUS_LABELS, type UserStatus } from '@/modules/users/types/user';

export type StatusTone = 'positive' | 'attention' | 'neutral' | 'danger' | 'info';

export type StatusKind = 'vehicle' | 'employee' | 'trip' | 'maintenance' | 'user';

interface StatusEntry {
    label: string;
    tone: StatusTone;
}

/** Um lugar só para o significado visual de cada status: verde roda, âmbar pede atenção, cinza está parado. */
export const STATUS_CATALOG: {
    vehicle: Record<VehicleStatus, StatusEntry>;
    employee: Record<EmployeeStatus, StatusEntry>;
    trip: Record<TripStatus, StatusEntry>;
    maintenance: Record<MaintenanceStatus, StatusEntry>;
    user: Record<UserStatus, StatusEntry>;
} = {
    vehicle: {
        active: { label: VEHICLE_STATUS_LABELS.active, tone: 'positive' },
        maintenance: { label: VEHICLE_STATUS_LABELS.maintenance, tone: 'attention' },
        inactive: { label: VEHICLE_STATUS_LABELS.inactive, tone: 'neutral' },
    },
    employee: {
        active: { label: EMPLOYEE_STATUS_LABELS.active, tone: 'positive' },
        on_leave: { label: EMPLOYEE_STATUS_LABELS.on_leave, tone: 'attention' },
        inactive: { label: EMPLOYEE_STATUS_LABELS.inactive, tone: 'neutral' },
    },
    trip: {
        scheduled: { label: TRIP_STATUS_LABELS.scheduled, tone: 'info' },
        in_progress: { label: TRIP_STATUS_LABELS.in_progress, tone: 'attention' },
        completed: { label: TRIP_STATUS_LABELS.completed, tone: 'positive' },
        canceled: { label: TRIP_STATUS_LABELS.canceled, tone: 'danger' },
    },
    maintenance: {
        open: { label: MAINTENANCE_STATUS_LABELS.open, tone: 'attention' },
        in_progress: { label: MAINTENANCE_STATUS_LABELS.in_progress, tone: 'info' },
        completed: { label: MAINTENANCE_STATUS_LABELS.completed, tone: 'positive' },
        canceled: { label: MAINTENANCE_STATUS_LABELS.canceled, tone: 'neutral' },
    },
    user: {
        active: { label: USER_STATUS_LABELS.active, tone: 'positive' },
        inactive: { label: USER_STATUS_LABELS.inactive, tone: 'neutral' },
    },
};

/** Status que a API mandar e o catálogo ainda não conhecer aparece em cinza, com o próprio texto. */
export function resolveStatus(kind: StatusKind, status: string): StatusEntry {
    const entries: Record<string, StatusEntry | undefined> = STATUS_CATALOG[kind];

    return entries[status] ?? { label: status, tone: 'neutral' };
}
