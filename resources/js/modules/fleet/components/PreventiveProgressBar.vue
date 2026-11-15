<script setup lang="ts">
import { computed } from 'vue';
import { formatKm } from '@/core/utils/format';
import { preventiveProgress, type Vehicle } from '../types/vehicle';

const props = defineProps<{ vehicle: Pick<Vehicle, 'current_mileage' | 'next_preventive_maintenance_km'> }>();

const progress = computed(() => preventiveProgress(props.vehicle));
</script>

<template>
    <span v-if="progress === null" class="text-sm text-ink-400" data-test="preventive-none">Não programada</span>
    <div v-else class="w-40" data-test="preventive-progress" :data-state="progress.due ? 'due' : progress.near ? 'near' : 'ok'">
        <div
            class="h-1.5 overflow-hidden rounded-full bg-ink-100"
            role="progressbar"
            :aria-valuenow="progress.percent"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="Quilometragem rodada até a próxima revisão preventiva"
        >
            <div
                class="h-full rounded-full"
                :class="progress.due ? 'bg-brake-500' : progress.near ? 'bg-signal-500' : 'bg-road-500'"
                :style="{ width: `${progress.percent}%` }"
            />
        </div>
        <p class="num mt-1 text-xs" :class="progress.due ? 'font-semibold text-brake-700' : progress.near ? 'text-signal-700' : 'text-ink-500'">
            <template v-if="progress.due">Vencida há {{ formatKm(-progress.remainingKm) }}</template>
            <template v-else>Faltam {{ formatKm(progress.remainingKm) }}</template>
        </p>
    </div>
</template>
