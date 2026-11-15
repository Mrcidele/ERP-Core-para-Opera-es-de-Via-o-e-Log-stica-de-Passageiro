<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue';

export type StatTone = 'neutral' | 'positive' | 'attention' | 'danger';

withDefaults(
    defineProps<{
        label: string;
        /** Valor em destaque, já formatado. */
        value: string;
        /** Contexto do número: de quanto, em que período. */
        subtitle?: string;
        icon: IconName;
        tone?: StatTone;
        /** Texto curto do indicador de alerta (ex.: "3 vencidas"); sem ele, o indicador não aparece. */
        alert?: string;
    }>(),
    { tone: 'neutral' },
);

const ICON_STYLES: Record<StatTone, string> = {
    neutral: 'bg-ink-100 text-ink-600',
    positive: 'bg-road-50 text-road-600',
    attention: 'bg-signal-50 text-signal-600',
    danger: 'bg-brake-50 text-brake-600',
};
</script>

<template>
    <article class="card flex flex-col p-5" data-test="stat-card">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-ink-500">{{ label }}</p>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="ICON_STYLES[tone]">
                <AppIcon :name="icon" />
            </span>
        </div>
        <p class="num mt-2 text-3xl font-semibold text-ink-950" data-test="stat-value">{{ value }}</p>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <span v-if="subtitle" class="text-ink-500" data-test="stat-subtitle">{{ subtitle }}</span>
            <span
                v-if="alert"
                class="inline-flex items-center gap-1 rounded-full bg-signal-50 px-2 py-0.5 text-xs font-semibold text-signal-700 ring-1 ring-inset ring-signal-600/25"
                data-test="stat-alert"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-signal-500" aria-hidden="true" />
                {{ alert }}
            </span>
        </div>
    </article>
</template>
