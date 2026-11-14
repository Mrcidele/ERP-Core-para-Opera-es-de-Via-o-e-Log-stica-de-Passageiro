import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import type { Employee } from '@/modules/crew/types/employee';
import type { Vehicle } from '@/modules/fleet/types/vehicle';
import type { ScheduleTripPayload } from '../schemas/scheduleTripSchema';
import type { Line, Trip } from '../types/trip';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

export const useTripStore = defineStore('operations/trips', () => {
    // Opções do formulário: só o que pode ser escalado (veículos ativos, motoristas ativos).
    const lines = ref<Line[]>([]);
    const vehicles = ref<Vehicle[]>([]);
    const drivers = ref<Employee[]>([]);
    const optionsStatus = ref<RequestStatus>('idle');

    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);
    const lastScheduled = ref<Trip | null>(null);

    async function loadOptions(): Promise<void> {
        optionsStatus.value = 'loading';

        try {
            const [lineList, vehicleList, employeeList] = await Promise.all([
                http.get<{ data: Line[] }>('/operations/lines'),
                http.get<{ data: Vehicle[] }>('/vehicles'),
                http.get<{ data: Employee[] }>('/crew/employees'),
            ]);

            lines.value = lineList.data.data;
            vehicles.value = vehicleList.data.data.filter((vehicle) => vehicle.status === 'active');
            drivers.value = employeeList.data.data.filter((employee) => employee.role === 'driver' && employee.status === 'active');
            optionsStatus.value = 'success';
        } catch {
            optionsStatus.value = 'error';
        }
    }

    function resetSaveState(): void {
        saveStatus.value = 'idle';
        validationErrors.value = {};
        saveError.value = null;
    }

    async function schedule(payload: ScheduleTripPayload): Promise<Trip | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const response = await http.post<{ data: Trip }>('/operations/trips', payload);

            lastScheduled.value = response.data.data;
            saveStatus.value = 'success';

            return lastScheduled.value;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
            } else {
                saveError.value = extractErrorMessage(error, 'Não foi possível agendar a viagem. Tente novamente.');
            }

            saveStatus.value = 'error';

            return null;
        }
    }

    return {
        lines,
        vehicles,
        drivers,
        optionsStatus,
        saveStatus,
        validationErrors,
        saveError,
        lastScheduled,
        loadOptions,
        resetSaveState,
        schedule,
    };
});
