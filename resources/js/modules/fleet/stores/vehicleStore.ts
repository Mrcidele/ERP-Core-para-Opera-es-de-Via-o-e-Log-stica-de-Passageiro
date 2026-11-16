import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import { usePagedList, type RequestStatus } from '@/core/api/pagination';
import type { CreateVehiclePayload, UpdateVehiclePayload } from '../schemas/createVehicleSchema';
import type { Vehicle } from '../types/vehicle';

export type { RequestStatus };

export const useVehicleStore = defineStore('fleet/vehicles', () => {
    // A lista vem paginada do servidor, com busca por placa ou chassi e filtro por status.
    const list = usePagedList<Vehicle>('/fleet/vehicles');

    /** Filtro "revisão vencida": só veículos que já alcançaram a quilometragem da próxima preventiva. */
    const onlyDue = computed(() => list.extra.value.needs_maintenance === '1');

    const showOnlyDue = (enabled: boolean): Promise<void> => list.applyFilters({ extra: enabled ? { needs_maintenance: '1' } : {} });

    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);

    const removeStatus = ref<RequestStatus>('idle');
    const removeError = ref<string | null>(null);

    function resetSaveState(): void {
        saveStatus.value = 'idle';
        validationErrors.value = {};
        saveError.value = null;
    }

    async function save(request: () => Promise<{ data: { data: Vehicle } }>, failure: string): Promise<Vehicle | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const vehicle = (await request()).data.data;

            saveStatus.value = 'success';
            // A ordem e a página de cada veículo são do servidor: recarrega a página atual.
            await list.fetchPage();

            return vehicle;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
            } else {
                saveError.value = extractErrorMessage(error, failure);
            }

            saveStatus.value = 'error';

            return null;
        }
    }

    const create = (payload: CreateVehiclePayload): Promise<Vehicle | null> =>
        save(() => http.post('/fleet/vehicles', payload), 'Não foi possível cadastrar o veículo. Tente novamente.');

    const update = (id: string, payload: UpdateVehiclePayload): Promise<Vehicle | null> =>
        save(() => http.put(`/fleet/vehicles/${id}`, payload), 'Não foi possível salvar as alterações. Tente novamente.');

    async function remove(id: string): Promise<boolean> {
        removeStatus.value = 'loading';
        removeError.value = null;

        try {
            await http.delete(`/fleet/vehicles/${id}`);

            removeStatus.value = 'success';
            await list.fetchPage();

            return true;
        } catch (error) {
            removeError.value = extractErrorMessage(error, 'Não foi possível excluir o veículo. Tente novamente.');
            removeStatus.value = 'error';

            return false;
        }
    }

    return {
        vehicles: list.items,
        meta: list.meta,
        search: list.search,
        status: list.status,
        onlyDue,
        showOnlyDue,
        listStatus: list.listStatus,
        refreshing: list.refreshing,
        filtering: list.filtering,
        fetchAll: list.fetchPage,
        applyFilters: list.applyFilters,
        saveStatus,
        validationErrors,
        saveError,
        removeStatus,
        removeError,
        resetSaveState,
        create,
        update,
        remove,
    };
});
