<script setup lang="ts">
import { computed } from 'vue';

// Itinerário de uma linha em escala: a origem à esquerda, o destino à direita e cada parada
// na posição proporcional ao km em que fica.
const props = defineProps<{
    distanceKm: number;
    stops: readonly { city_name: string; cumulative_km: number }[];
}>();

const marks = computed(() =>
    props.stops
        .filter((stop) => props.distanceKm > 0 && stop.cumulative_km > 0 && stop.cumulative_km < props.distanceKm)
        .map((stop) => ({
            label: `${stop.city_name}, km ${stop.cumulative_km}`,
            left: `${(stop.cumulative_km / props.distanceKm) * 100}%`,
        })),
);
</script>

<template>
    <div class="relative h-3" role="img" :aria-label="marks.length === 0 ? 'Sem paradas intermediárias' : `Paradas: ${marks.map((mark) => mark.label).join('; ')}`">
        <span class="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 rounded-full bg-ink-200" />
        <span class="absolute left-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full border-2 border-ink-900 bg-white" />
        <span
            v-for="mark in marks"
            :key="mark.label"
            data-test="route-mark"
            class="absolute top-1/2 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-signal-500 ring-2 ring-white"
            :style="{ left: mark.left }"
            :title="mark.label"
        />
        <span class="absolute right-0 top-1/2 h-3 w-3 -translate-y-1/2 rounded-full bg-ink-900" />
    </div>
</template>
