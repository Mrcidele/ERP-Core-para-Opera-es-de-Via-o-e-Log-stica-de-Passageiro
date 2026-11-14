<script setup lang="ts">
export type Tone = 'positive' | 'attention' | 'neutral' | 'danger';

defineProps<{ tone: Tone; label: string }>();

const STYLES: Record<Tone, string> = {
    positive: 'bg-road-100 text-road-800',
    attention: 'bg-signal-100 text-signal-700',
    neutral: 'bg-ink-100 text-ink-600',
    danger: 'bg-brake-100 text-brake-700',
};

const DOTS: Record<Tone, string> = {
    positive: 'bg-road-600',
    attention: 'bg-signal-500',
    neutral: 'bg-ink-400',
    danger: 'bg-brake-600',
};
</script>

<template>
    <span class="inline-flex items-center gap-2 whitespace-nowrap rounded-full px-2.5 py-1 text-sm font-medium" :class="STYLES[tone]">
        <span class="h-2 w-2 rounded-full" :class="DOTS[tone]" />
        {{ label }}
    </span>
</template>
