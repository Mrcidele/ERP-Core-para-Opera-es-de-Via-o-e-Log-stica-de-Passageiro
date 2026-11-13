<script setup lang="ts">
import { VEHICLE_STATUS_LABELS, type VehicleStatus } from '../types/vehicle';

defineProps<{ status: VehicleStatus }>();

const STYLES: Record<VehicleStatus, string> = {
    active: 'bg-road-100 text-road-800',
    maintenance: 'bg-signal-100 text-signal-700',
    inactive: 'bg-ink-100 text-ink-600',
};

const DOTS: Record<VehicleStatus, string> = {
    active: 'bg-road-600',
    maintenance: 'bg-signal-500',
    inactive: 'bg-ink-400',
};
</script>

<template>
    <span class="inline-flex items-center gap-2 whitespace-nowrap rounded-full px-2.5 py-1 text-sm font-medium" :class="STYLES[status]">
        <span class="h-2 w-2 rounded-full" :class="DOTS[status]" />
        {{ VEHICLE_STATUS_LABELS[status] }}
    </span>
</template>
