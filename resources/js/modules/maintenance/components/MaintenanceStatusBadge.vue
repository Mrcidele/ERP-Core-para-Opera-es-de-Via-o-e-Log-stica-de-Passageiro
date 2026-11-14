<script setup lang="ts">
import ToneBadge, { type Tone } from '@/core/components/ToneBadge.vue';
import { MAINTENANCE_STATUS_LABELS, type MaintenanceStatus } from '../types/maintenance';

defineProps<{ status: MaintenanceStatus }>();

const TONES: Record<MaintenanceStatus, Tone> = {
    open: 'attention',
    in_progress: 'attention',
    completed: 'positive',
    canceled: 'neutral',
};
</script>

<template>
    <ToneBadge :tone="TONES[status]" :label="MAINTENANCE_STATUS_LABELS[status]" />
</template>
