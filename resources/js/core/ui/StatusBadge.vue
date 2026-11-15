<script setup lang="ts">
import { computed } from 'vue';
import { resolveStatus, type StatusKind, type StatusTone } from './statusCatalog';

const props = defineProps<{
    /** De que entidade é o status: veículo, funcionário, viagem ou ordem de serviço. */
    kind: StatusKind;
    status: string;
}>();

const entry = computed(() => resolveStatus(props.kind, props.status));

const STYLES: Record<StatusTone, string> = {
    positive: 'bg-road-50 text-road-800 ring-road-600/20',
    attention: 'bg-signal-50 text-signal-700 ring-signal-600/25',
    neutral: 'bg-ink-100 text-ink-600 ring-ink-500/15',
    danger: 'bg-brake-50 text-brake-700 ring-brake-600/20',
    info: 'bg-sky-50 text-sky-800 ring-sky-600/20',
};

const DOTS: Record<StatusTone, string> = {
    positive: 'bg-road-500',
    attention: 'bg-signal-500',
    neutral: 'bg-ink-400',
    danger: 'bg-brake-500',
    info: 'bg-sky-500',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="STYLES[entry.tone]"
        data-test="status-badge"
        :data-tone="entry.tone"
    >
        <span class="h-1.5 w-1.5 rounded-full" :class="DOTS[entry.tone]" aria-hidden="true" />
        {{ entry.label }}
    </span>
</template>
