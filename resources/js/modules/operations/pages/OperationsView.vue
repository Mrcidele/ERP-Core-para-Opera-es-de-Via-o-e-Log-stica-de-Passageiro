<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { countLabel, formatDateTime, formatKm } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import TripForm from '../components/TripForm.vue';
import TripTable from '../components/TripTable.vue';
import { useTripStore } from '../stores/tripStore';
import { TRIP_STATUS_LABELS, TRIP_STATUSES, type Trip, type TripStatus, type TripSummary, type TripTransition } from '../types/trip';

const store = useTripStore();
const auth = useAuthStore();
const toast = useToast();

// Sem operations.manage a tela é só de consulta.
const canManage = computed(() => auth.can('operations.manage'));

const statusFilter = ref<TripStatus | 'all'>('all');
const formOpen = ref(false);
const canceling = ref<TripSummary | null>(null);

onMounted(() => store.fetchTrips());

const filters = computed(() => [
    { value: 'all' as const, label: 'Todas', count: store.trips.length },
    ...TRIP_STATUSES.map((status) => ({
        value: status,
        label: TRIP_STATUS_LABELS[status],
        count: store.trips.filter((trip) => trip.status === status).length,
    })),
]);

const visibleTrips = computed(() => (statusFilter.value === 'all' ? store.trips : store.trips.filter((trip) => trip.status === statusFilter.value)));

const route = (trip: TripSummary): string => `${trip.line.origin} → ${trip.line.destination}`;

async function onScheduled(trip: Trip): Promise<void> {
    const line = store.lines.find((item) => item.id === trip.line_id);

    toast.success(`${line ? `${line.origin} → ${line.destination}` : 'Viagem'} agendada para ${formatDateTime(trip.departure_time)}.`);
    formOpen.value = false;

    await store.fetchTrips();
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
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-trip" @click="formOpen = true">
            <AppIcon name="plus" />
            Agendar viagem
        </button>
    </header>

    <p
        v-if="store.transitionError && !canceling"
        role="alert"
        data-test="transition-error"
        class="mt-6 rounded-lg border border-brake-600/20 bg-brake-50 px-4 py-3 text-sm font-medium text-brake-700"
    >
        {{ store.transitionError }}
    </p>

    <section class="card mt-6 overflow-hidden">
        <div class="border-b border-ink-200 p-3">
            <div role="group" aria-label="Filtrar por status" class="segmented">
                <button
                    v-for="filter in filters"
                    :key="filter.value"
                    type="button"
                    :data-test="`filter-${filter.value}`"
                    :aria-pressed="statusFilter === filter.value"
                    @click="statusFilter = filter.value"
                >
                    {{ filter.label }}
                    <span class="num ml-1 text-xs text-ink-400">{{ filter.count }}</span>
                </button>
            </div>
        </div>

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
            v-else-if="store.trips.length === 0"
            data-test="empty"
            icon="route"
            title="Nenhuma viagem agendada"
            :description="canManage ? 'Agende a primeira escolhendo linha, veículo e motorista.' : 'Ainda não há viagens na escala.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="formOpen = true">Agendar viagem</button>
        </EmptyState>

        <EmptyState v-else-if="visibleTrips.length === 0" data-test="no-results" icon="search" title="Nenhuma viagem com esse status">
            <button type="button" class="btn btn-quiet" @click="statusFilter = 'all'">Ver todas</button>
        </EmptyState>

        <template v-else>
            <TripTable :trips="visibleTrips" :busy-id="store.transitioningId" :can-manage="canManage" @transition="onTransition" />
            <p class="border-t border-ink-200 px-5 py-2.5 text-xs text-ink-500">
                <template v-if="statusFilter !== 'all'">{{ visibleTrips.length }} de {{ countLabel(store.trips.length, 'viagem', 'viagens') }}</template>
                <template v-else>{{ countLabel(store.trips.length, 'viagem', 'viagens') }}</template>
            </p>
        </template>
    </section>

    <SlideOver :open="formOpen" title="Agendar viagem" subtitle="O motorista precisa de 11 horas de descanso entre viagens." @close="formOpen = false">
        <TripForm @scheduled="onScheduled" @cancel="formOpen = false" />
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
