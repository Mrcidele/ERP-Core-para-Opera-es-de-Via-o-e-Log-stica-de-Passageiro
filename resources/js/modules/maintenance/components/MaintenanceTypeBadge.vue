<script setup lang="ts">
import { MAINTENANCE_TYPE_LABELS, type MaintenanceType } from '../types/maintenance';

defineProps<{ type: MaintenanceType }>();

// Corretiva é o conserto de uma falha; preventiva é revisão programada.
const STYLES: Record<MaintenanceType, string> = {
    preventive: 'border-ink-200 bg-white text-ink-700',
    corrective: 'border-brake-600/30 bg-brake-100 text-brake-700',
};
</script>

<template>
    <span class="inline-block whitespace-nowrap rounded border px-2 py-0.5 text-sm font-medium" :class="STYLES[type]" data-test="type-badge">
        {{ MAINTENANCE_TYPE_LABELS[type] }}
    </span>
</template>
