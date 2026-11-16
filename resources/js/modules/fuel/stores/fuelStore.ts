import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import { fetchEveryPage, usePagedList, type RequestStatus } from '@/core/api/pagination';
import type { Vehicle } from '@/modules/fleet/types/vehicle';
import type { FuelLogPayload } from '../schemas/fuelLogSchema';
import type { FuelLog } from '../types/fuelLog';

export const useFuelStore = defineStore('fuel/logs', () => {
    // Os abastecimentos vêm paginados do servidor, do mais recente para o mais antigo, com busca por placa ou posto.
    const list = usePagedList<FuelLog>('/fuel/logs');

    // Veículos do formulário: qualquer um não excluído pode ser abastecido, mesmo em manutenção.
    const vehicles = ref<Vehicle[]>([]);
    const optionsStatus = ref<RequestStatus>('idle');

    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);

    async function loadOptions(): Promise<void> {
        optionsStatus.value = 'loading';

        try {
            vehicles.value = await fetchEveryPage<Vehicle>('/fleet/vehicles');
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

    async function record(payload: FuelLogPayload): Promise<FuelLog | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const response = await http.post<{ data: FuelLog }>('/fuel/logs', payload);

            saveStatus.value = 'success';
            // O registro novo entra no topo da primeira página; os rendimentos são calculados pelo servidor.
            await list.fetchPage(1);

            return response.data.data;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
            } else {
                saveError.value = extractErrorMessage(error, 'Não foi possível registrar o abastecimento. Tente novamente.');
            }

            saveStatus.value = 'error';

            return null;
        }
    }

    return {
        logs: list.items,
        meta: list.meta,
        search: list.search,
        listStatus: list.listStatus,
        refreshing: list.refreshing,
        filtering: list.filtering,
        fetchLogs: list.fetchPage,
        applyFilters: list.applyFilters,
        vehicles,
        optionsStatus,
        loadOptions,
        saveStatus,
        validationErrors,
        saveError,
        resetSaveState,
        record,
    };
});
