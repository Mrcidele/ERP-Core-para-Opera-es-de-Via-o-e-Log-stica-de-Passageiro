<script setup lang="ts">
import { ref } from 'vue';
import AppIcon from '@/core/components/AppIcon.vue';
import SlideOver from '@/core/components/SlideOver.vue';
import { formatDateTime } from '@/core/utils/format';
import TripForm from '../components/TripForm.vue';
import { useTripStore } from '../stores/tripStore';
import type { Trip } from '../types/trip';

const store = useTripStore();

const formOpen = ref(false);
const notice = ref<string | null>(null);

function onScheduled(trip: Trip): void {
    const line = store.lines.find((item) => item.id === trip.line_id);
    const route = line ? `${line.origin} → ${line.destination}` : 'Viagem';

    notice.value = `${route} agendada para ${formatDateTime(trip.departure_time)}.`;
    formOpen.value = false;
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">Operações</h1>
            <p class="mt-1 text-ink-500">Viagens agendadas por linha, veículo e motorista.</p>
        </div>
        <button type="button" class="btn btn-primary" data-test="new-trip" @click="formOpen = true">
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

    <section class="mt-6 max-w-xl rounded-xl border border-dashed border-ink-200 bg-white p-8">
        <h2 class="font-display text-lg font-semibold">A lista de viagens ainda não está disponível</h2>
        <p class="mt-2 text-ink-600">
            Por enquanto é possível agendar. O sistema recusa a viagem se o veículo ou o motorista já tiver outra no mesmo período.
        </p>
    </section>

    <SlideOver :open="formOpen" title="Agendar viagem" @close="formOpen = false">
        <TripForm @scheduled="onScheduled" @cancel="formOpen = false" />
    </SlideOver>
</template>
