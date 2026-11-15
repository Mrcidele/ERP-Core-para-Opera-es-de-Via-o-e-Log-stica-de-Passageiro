<script setup lang="ts">
import { MAINTENANCE_TYPE_LABELS, type MaintenanceType } from '../types/maintenance';

defineProps<{ type: MaintenanceType }>();

// Corretiva é o conserto de uma falha; preventiva é revisão programada.
const STYLES: Record<MaintenanceType, string> = {
    preventive: 'bg-white text-ink-700 ring-ink-300',
    corrective: 'bg-brake-50 text-brake-700 ring-brake-600/25',
};
</script>

<template>
    <span class="inline-block whitespace-nowrap rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="STYLES[type]" data-test="type-badge">
        {{ MAINTENANCE_TYPE_LABELS[type] }}
    </span>
</template>
