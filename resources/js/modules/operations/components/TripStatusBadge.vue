<script setup lang="ts">
import ToneBadge, { type Tone } from '@/core/components/ToneBadge.vue';
import { TRIP_STATUS_LABELS, type TripStatus } from '../types/trip';

defineProps<{ status: TripStatus }>();

const TONES: Record<TripStatus, Tone> = {
    scheduled: 'neutral',
    in_progress: 'attention',
    completed: 'positive',
    canceled: 'danger',
};
</script>

<template>
    <ToneBadge :tone="TONES[status]" :label="TRIP_STATUS_LABELS[status]" />
</template>
