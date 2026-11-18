import { defineStore } from 'pinia';
import { ref } from 'vue';
import { downloadFile } from '@/core/api/download';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import { fetchEveryPage, usePagedList, type RequestStatus } from '@/core/api/pagination';
import type { Employee } from '@/modules/crew/types/employee';
import type { Vehicle } from '@/modules/fleet/types/vehicle';
import type { ScheduleTripPayload } from '../schemas/scheduleTripSchema';
import type { Line, Trip, TripSummary, TripTransition } from '../types/trip';

export type { RequestStatus };

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

    // A escala vem paginada do servidor, com busca por rota, placa ou motorista e filtro por status.
    const list = usePagedList<TripSummary>('/operations/trips');

    /** Id da viagem cuja mudança de status está em andamento. */
    const transitioningId = ref<string | null>(null);
    const transitionError = ref<string | null>(null);

    const exportStatus = ref<RequestStatus>('idle');

    /** Baixa o CSV com tudo o que atende aos filtros da tela (não só a página visível). */
    async function exportCsv(): Promise<boolean> {
        exportStatus.value = 'loading';

        try {
            await downloadFile('/operations/trips/export', list.filterParams(), 'manifesto-de-viagens.csv');
            exportStatus.value = 'success';

            return true;
        } catch {
            exportStatus.value = 'error';

            return false;
        }
    }

    /** Inicia, conclui ou cancela uma viagem; devolve se deu certo. */
    async function transition(id: string, action: TripTransition): Promise<boolean> {
        transitioningId.value = id;
        transitionError.value = null;

        try {
            await http.post<{ data: Trip }>(`/operations/trips/${id}/${action}`);
            // Com filtro por status, a viagem pode sair da lista: quem decide é o servidor.
            await list.fetchPage();

            return true;
        } catch (error) {
            const errors = extractValidationErrors(error);

            transitionError.value =
                errors?.status?.[0] ?? extractErrorMessage(error, 'Não foi possível atualizar a viagem. Tente novamente.');

            return false;
        } finally {
            transitioningId.value = null;
        }
    }

    async function loadOptions(): Promise<void> {
        optionsStatus.value = 'loading';

        try {
            // Veículos e funcionários são paginados: o formulário precisa de todos os ativos, não de uma página.
            const [lineList, activeVehicles, activeEmployees] = await Promise.all([
                http.get<{ data: Line[] }>('/operations/lines'),
                fetchEveryPage<Vehicle>('/fleet/vehicles', { status: 'active' }),
                fetchEveryPage<Employee>('/crew/employees', { status: 'active' }),
            ]);

            lines.value = lineList.data.data;
            vehicles.value = activeVehicles;
            drivers.value = activeEmployees.filter((employee) => employee.role === 'driver');
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
        trips: list.items,
        meta: list.meta,
        search: list.search,
        status: list.status,
        listStatus: list.listStatus,
        refreshing: list.refreshing,
        filtering: list.filtering,
        applyFilters: list.applyFilters,
        exportStatus,
        exportCsv,
        transitioningId,
        transitionError,
        lines,
        vehicles,
        drivers,
        optionsStatus,
        saveStatus,
        validationErrors,
        saveError,
        lastScheduled,
        fetchTrips: list.fetchPage,
        transition,
        loadOptions,
        resetSaveState,
        schedule,
    };
});
