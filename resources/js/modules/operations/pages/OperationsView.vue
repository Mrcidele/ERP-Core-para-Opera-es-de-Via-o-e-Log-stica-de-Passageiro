<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import ListToolbar from '@/core/ui/ListToolbar.vue';
import PaginationBar from '@/core/ui/PaginationBar.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { formatDateTime, formatKm } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import ScheduleGrid from '../components/ScheduleGrid.vue';
import TripForm from '../components/TripForm.vue';
import TripTable from '../components/TripTable.vue';
import { useTimelineStore } from '../stores/timelineStore';
import { useTripStore } from '../stores/tripStore';
import type { TripPrefill } from '../types/timeline';
import { TRIP_STATUS_LABELS, TRIP_STATUSES, type Trip, type TripSummary, type TripTransition } from '../types/trip';

const store = useTripStore();
const auth = useAuthStore();
const toast = useToast();

// Sem operations.manage a tela é só de consulta.
const canManage = computed(() => auth.can('operations.manage'));

const formOpen = ref(false);
const canceling = ref<TripSummary | null>(null);

// Duas leituras da mesma escala: a lista paginada e a grade por veículo e motorista.
const view = ref<'list' | 'grid'>('list');
const timeline = useTimelineStore();
/** Veículo, motorista e horário escolhidos na grade; vazio quando o formulário abre pelo botão. */
const prefill = ref<TripPrefill>({});
const formSession = ref(0);

onMounted(() => store.fetchTrips());

function show(mode: 'list' | 'grid'): void {
    view.value = mode;

    if (mode === 'grid') {
        void timeline.fetch();
    }
}

function openForm(initial: TripPrefill = {}): void {
    prefill.value = initial;
    formSession.value += 1;
    formOpen.value = true;
}

const STATUS_OPTIONS = TRIP_STATUSES.map((status) => ({ value: status, label: TRIP_STATUS_LABELS[status] }));

async function exportCsv(): Promise<void> {
    if (await store.exportCsv()) {
        toast.success('Manifesto de viagens exportado com os filtros da tela.');
    } else {
        toast.error('Não foi possível exportar as viagens. Tente novamente.');
    }
}

const route = (trip: TripSummary): string => `${trip.line.origin} → ${trip.line.destination}`;

async function onScheduled(trip: Trip): Promise<void> {
    const line = store.lines.find((item) => item.id === trip.line_id);

    toast.success(`${line ? `${line.origin} → ${line.destination}` : 'Viagem'} agendada para ${formatDateTime(trip.departure_time)}.`);
    formOpen.value = false;

    await Promise.all([store.fetchTrips(), view.value === 'grid' ? timeline.fetch() : Promise.resolve()]);
}

async function onTransition(trip: TripSummary, action: TripTransition): Promise<void> {
    if (action === 'cancel') {
        store.transitionError = null;
        canceling.value = trip;

        return;
    }

    if (await store.transition(trip.id, action)) {
        toast.success(
            action === 'start'
                ? `${route(trip)} iniciada.`
                : `${route(trip)} concluída. ${formatKm(trip.line.distance_km)} somados à quilometragem do veículo ${trip.vehicle.plate}.`,
        );
    }
}

async function confirmCancel(): Promise<void> {
    const trip = canceling.value;

    if (trip && (await store.transition(trip.id, 'cancel'))) {
        toast.success(`${route(trip)} cancelada.`);
        canceling.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Operações</h1>
            <p class="mt-1 text-sm text-ink-500">Viagens por linha, veículo e motorista, nos horários deste computador.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div role="group" aria-label="Visualização da escala" class="segmented">
                <button type="button" data-test="view-list" :aria-pressed="view === 'list'" @click="show('list')">Lista</button>
                <button type="button" data-test="view-grid" :aria-pressed="view === 'grid'" @click="show('grid')">Grade de escala</button>
            </div>
            <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-trip" @click="openForm()">
                <AppIcon name="plus" />
                Agendar viagem
            </button>
        </div>
    </header>

    <p
        v-if="store.transitionError && !canceling"
        role="alert"
        data-test="transition-error"
        class="mt-6 rounded-lg border border-brake-600/20 bg-brake-50 px-4 py-3 text-sm font-medium text-brake-700"
    >
        {{ store.transitionError }}
    </p>

    <section v-if="view === 'list'" class="card mt-6 overflow-hidden" data-test="list-view">
        <ListToolbar
            :statuses="STATUS_OPTIONS"
            all-label="Todas"
            :status="store.status"
            :search="store.search"
            search-label="Buscar por cidade, placa ou motorista"
            @update:status="store.applyFilters({ status: $event })"
            @update:search="store.applyFilters({ search: $event })"
        >
            <button type="button" class="btn btn-quiet !h-9" data-test="export-csv" :disabled="store.exportStatus === 'loading'" @click="exportCsv">
                <AppIcon name="download" />
                {{ store.exportStatus === 'loading' ? 'Exportando…' : 'Exportar CSV' }}
            </button>
        </ListToolbar>

        <TableSkeleton v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" :columns="6" label="Carregando viagens" />

        <EmptyState
            v-else-if="store.listStatus === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar as viagens"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchTrips()">Tentar novamente</button>
        </EmptyState>

        <EmptyState
            v-else-if="store.trips.length === 0 && !store.filtering"
            data-test="empty"
            icon="route"
            title="Nenhuma viagem agendada"
            :description="canManage ? 'Agende a primeira escolhendo linha, veículo e motorista.' : 'Ainda não há viagens na escala.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="openForm()">Agendar viagem</button>
        </EmptyState>

        <EmptyState v-else-if="store.trips.length === 0" data-test="no-results" icon="search" title="Nenhuma viagem corresponde ao filtro">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="store.applyFilters({ search: '', status: null })">Limpar filtros</button>
        </EmptyState>

        <template v-else>
            <div :class="{ 'opacity-60': store.refreshing }" :aria-busy="store.refreshing">
                <TripTable :trips="store.trips" :busy-id="store.transitioningId" :can-manage="canManage" @transition="onTransition" />
            </div>
            <PaginationBar :meta="store.meta" singular="viagem" plural="viagens" :busy="store.refreshing" @change="store.fetchTrips($event)" />
        </template>
    </section>

    <section v-else class="card mt-6 overflow-hidden" data-test="grid-view">
        <div class="flex flex-wrap items-center gap-3 border-b border-ink-200 p-3">
            <div class="flex items-center gap-1">
                <button type="button" class="icon-btn" data-test="previous-day" aria-label="Dia anterior" @click="timeline.shiftDays(-1)"><AppIcon name="chevron-left" /></button>
                <label class="sr-only" for="timeline-date">Dia da grade</label>
                <input
                    id="timeline-date"
                    type="date"
                    class="field-input num !mt-0 !h-9 !w-auto"
                    data-test="timeline-date"
                    :value="timeline.date"
                    @change="timeline.showDate(($event.target as HTMLInputElement).value)"
                />
                <button type="button" class="icon-btn" data-test="next-day" aria-label="Dia seguinte" @click="timeline.shiftDays(1)"><AppIcon name="chevron-right" /></button>
                <button type="button" class="btn btn-quiet btn-sm ml-1 !h-9" data-test="today" @click="timeline.showToday()">Hoje</button>
            </div>

            <ul class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-600" aria-label="Legenda">
                <li class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm bg-ink-800" />Agendada</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm bg-signal-500" />Em andamento</li>
                <li class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm bg-ink-200" />Concluída</li>
                <li class="flex items-center gap-1.5"><span class="timeline-rest h-3 w-5 rounded-sm" />Descanso de 11 h</li>
                <li class="flex items-center gap-1.5"><span class="timeline-maintenance h-3 w-5 rounded-sm" />Oficina</li>
            </ul>
        </div>

        <TableSkeleton v-if="timeline.status === 'loading' || timeline.status === 'idle'" data-test="grid-loading" :columns="6" label="Carregando a grade de escala" />

        <EmptyState
            v-else-if="timeline.status === 'error' || !timeline.timeline"
            data-test="grid-error"
            icon="alert"
            title="Não foi possível carregar a grade de escala"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="timeline.fetch()">Tentar novamente</button>
        </EmptyState>

        <template v-else>
            <div :class="{ 'opacity-60': timeline.refreshing }" :aria-busy="timeline.refreshing">
                <ScheduleGrid :timeline="timeline.timeline" :start="timeline.start" :can-schedule="canManage" @schedule="openForm" />
            </div>
            <p v-if="canManage" class="border-t border-ink-200 px-5 py-2.5 text-xs text-ink-500" data-test="grid-hint">
                Clique em um horário livre de um veículo ou de um motorista para agendar uma viagem a partir dali.
            </p>
        </template>
    </section>

    <SlideOver :open="formOpen" title="Agendar viagem" subtitle="O motorista precisa de 11 horas de descanso entre viagens." @close="formOpen = false">
        <TripForm :key="formSession" :initial="prefill" @scheduled="onScheduled" @cancel="formOpen = false" />
    </SlideOver>

    <ConfirmDialog
        :open="canceling !== null"
        :title="canceling ? `Cancelar ${route(canceling)}?` : ''"
        :message="canceling ? `Partida em ${formatDateTime(canceling.departure_time)}. O veículo e o motorista ficam livres nesse período.` : ''"
        confirm-label="Cancelar viagem"
        dismiss-label="Voltar"
        :busy="store.transitioningId !== null"
        :error="store.transitionError"
        @confirm="confirmCancel"
        @cancel="canceling = null"
    />
</template>
