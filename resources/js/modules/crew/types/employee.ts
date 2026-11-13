// Espelha App\Domain\Crew\Enums e App\Presentation\Http\Resources\Crew\EmployeeResource.

export const EMPLOYEE_ROLES = ['driver', 'mechanic', 'admin'] as const;
export type EmployeeRole = (typeof EMPLOYEE_ROLES)[number];

export const EMPLOYEE_STATUSES = ['active', 'on_leave', 'inactive'] as const;
export type EmployeeStatus = (typeof EMPLOYEE_STATUSES)[number];

export const CNH_CATEGORIES = ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'] as const;
export type CnhCategory = (typeof CNH_CATEGORIES)[number];

export interface Employee {
    id: string;
    name: string;
    /** Somente dígitos. */
    cpf: string;
    role: EmployeeRole;
    status: EmployeeStatus;
    cnh_number: string | null;
    cnh_category: CnhCategory | null;
    /** Data no formato AAAA-MM-DD. */
    cnh_expiration_date: string | null;
}

export const EMPLOYEE_ROLE_LABELS: Record<EmployeeRole, string> = {
    driver: 'Motorista',
    mechanic: 'Mecânico',
    admin: 'Administrativo',
};

export const EMPLOYEE_STATUS_LABELS: Record<EmployeeStatus, string> = {
    active: 'Ativo',
    on_leave: 'Afastado',
    inactive: 'Inativo',
};
