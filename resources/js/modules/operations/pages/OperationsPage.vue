<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/components/AppIcon.vue';
import ConfirmDialog from '@/core/components/ConfirmDialog.vue';
import SlideOver from '@/core/components/SlideOver.vue';
import { countLabel, formatDateTime, formatKm } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import TripForm from '../components/TripForm.vue';
import TripTable from '../components/TripTable.vue';
import { useTripStore } from '../stores/tripStore';
import type { Trip, TripSummary, TripTransition } from '../types/trip';

const store = useTripStore();
const auth = useAuthStore();

// Sem operations.manage a tela é só de consulta.
const canManage = computed(() => auth.can('operations.manage'));

const formOpen = ref(false);
const canceling = ref<TripSummary | null>(null);
const notice = ref<string | null>(null);

onMounted(() => store.fetchTrips());

const route = (trip: TripSummary): string => `${trip.line.origin} → ${trip.line.destination}`;

async function onScheduled(trip: Trip): Promise<void> {
    const line = store.lines.find((item) => item.id === trip.line_id);

    notice.value = `${line ? `${line.origin} → ${line.destination}` : 'Viagem'} agendada para ${formatDateTime(trip.departure_time)}.`;
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
        notice.value =
            action === 'start'
                ? `${route(trip)} iniciada.`
                : `${route(trip)} concluída. ${formatKm(trip.line.distance_km)} somados à quilometragem do veículo ${trip.vehicle.plate}.`;
    }
}

async function confirmCancel(): Promise<void> {
    const trip = canceling.value;

    if (trip && (await store.transition(trip.id, 'cancel'))) {
        notice.value = `${route(trip)} cancelada.`;
        canceling.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">Operações</h1>
            <p class="mt-1 text-ink-500">Viagens por linha, veículo e motorista, nos horários deste computador.</p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-trip" @click="formOpen = true">
            <AppIcon name="plus" />
            Agendar viagem
        </button>
    </header>

    <p
        v-if="notice"
        role="status"
        data-test="notice"
        class="mt-6 flex items-center justify-between gap-4 rounded-md bg-road-100 px-4 py-3 text-sm font-medium text-road-800"
    >
        {{ notice }}
        <button type="button" class="underline underline-offset-2" @click="notice = null">Dispensar</button>
    </p>

    <p
        v-if="store.transitionError && !canceling"
        role="alert"
        data-test="transition-error"
        class="mt-6 rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700"
    >
        {{ store.transitionError }}
    </p>

    <section class="mt-6 rounded-xl bg-white shadow-panel">
        <div v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" class="space-y-3 p-5" aria-busy="true">
            <div v-for="row in 5" :key="row" class="h-12 animate-pulse rounded-md bg-ink-50" />
            <span class="sr-only">Carregando viagens</span>
        </div>

        <div v-else-if="store.listStatus === 'error'" data-test="list-error" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Não foi possível carregar as viagens</h2>
            <p class="mt-1 text-ink-500">Verifique a conexão e tente de novo.</p>
            <button type="button" class="btn btn-quiet mt-5" @click="store.fetchTrips()">Tentar novamente</button>
        </div>

        <div v-else-if="store.trips.length === 0" data-test="empty" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Nenhuma viagem agendada</h2>
            <template v-if="canManage">
                <p class="mt-1 text-ink-500">Agende a primeira escolhendo linha, veículo e motorista.</p>
                <button type="button" class="btn btn-primary mt-5" @click="formOpen = true">Agendar viagem</button>
            </template>
        </div>

        <template v-else>
            <TripTable :trips="store.trips" :busy-id="store.transitioningId" :can-manage="canManage" @transition="onTransition" />
            <p class="border-t border-ink-100 px-5 py-3 text-sm text-ink-500">{{ countLabel(store.trips.length, 'viagem', 'viagens') }}</p>
        </template>
    </section>

    <SlideOver :open="formOpen" title="Agendar viagem" @close="formOpen = false">
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
