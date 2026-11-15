import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import type { Vehicle } from '@/modules/fleet/types/vehicle';
import type { CompleteMaintenanceOrderPayload, OpenMaintenanceOrderPayload } from '../schemas/maintenanceOrderSchemas';
import type { MaintenanceOrder, MaintenanceOrderSummary, Mechanic } from '../types/maintenance';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

export const useMaintenanceStore = defineStore('maintenance/orders', () => {
    const orders = ref<MaintenanceOrderSummary[]>([]);
    const listStatus = ref<RequestStatus>('idle');

    // Opções do formulário de abertura: qualquer veículo da frota e mecânicos ativos.
    const vehicles = ref<Vehicle[]>([]);
    const mechanics = ref<Mechanic[]>([]);
    const optionsStatus = ref<RequestStatus>('idle');

    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);

    async function fetchOrders(): Promise<void> {
        listStatus.value = 'loading';

        try {
            const response = await http.get<{ data: MaintenanceOrderSummary[] }>('/maintenance/orders');

            orders.value = response.data.data;
            listStatus.value = 'success';
        } catch {
            listStatus.value = 'error';
        }
    }

    async function loadOptions(): Promise<void> {
        optionsStatus.value = 'loading';

        try {
            // Os mecânicos vêm do próprio módulo: a supervisão de oficina não tem acesso à Tripulação.
            const [vehicleList, mechanicList] = await Promise.all([
                http.get<{ data: Vehicle[] }>('/fleet/vehicles'),
                http.get<{ data: Mechanic[] }>('/maintenance/mechanics'),
            ]);

            vehicles.value = vehicleList.data.data;
            mechanics.value = mechanicList.data.data;
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

    /** Executa a gravação e, se der certo, recarrega a lista (que traz placa e mecânico). */
    async function save(request: () => Promise<{ data: { data: MaintenanceOrder } }>, failure: string): Promise<MaintenanceOrder | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const order = (await request()).data.data;

            saveStatus.value = 'success';
            await fetchOrders();

            return order;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
                // Erros que não pertencem a um campo do formulário (ex.: ordem já encerrada).
                saveError.value = errors.status?.[0] ?? null;
            } else {
                saveError.value = extractErrorMessage(error, failure);
            }

            saveStatus.value = 'error';

            return null;
        }
    }

    const open = (payload: OpenMaintenanceOrderPayload): Promise<MaintenanceOrder | null> =>
        save(() => http.post('/maintenance/orders', payload), 'Não foi possível abrir a ordem de serviço. Tente novamente.');

    const complete = (id: string, payload: CompleteMaintenanceOrderPayload): Promise<MaintenanceOrder | null> =>
        save(() => http.patch(`/maintenance/orders/${id}`, payload), 'Não foi possível concluir a ordem de serviço. Tente novamente.');

    const cancel = (id: string): Promise<MaintenanceOrder | null> =>
        save(() => http.patch(`/maintenance/orders/${id}`, { status: 'canceled' }), 'Não foi possível cancelar a ordem de serviço. Tente novamente.');

    return {
        orders,
        listStatus,
        vehicles,
        mechanics,
        optionsStatus,
        saveStatus,
        validationErrors,
        saveError,
        fetchOrders,
        loadOptions,
        resetSaveState,
        open,
        complete,
        cancel,
    };
});
