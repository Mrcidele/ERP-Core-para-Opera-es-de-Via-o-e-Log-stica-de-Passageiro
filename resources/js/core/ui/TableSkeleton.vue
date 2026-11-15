<script setup lang="ts">
withDefaults(defineProps<{ rows?: number; columns?: number; label?: string }>(), { rows: 6, columns: 5, label: 'Carregando' });

// Larguras variadas para o esqueleto lembrar texto de verdade.
const WIDTHS = ['w-24', 'w-36', 'w-20', 'w-28', 'w-16', 'w-32'];
</script>

<template>
    <div aria-busy="true" class="divide-y divide-ink-100">
        <div class="flex gap-6 bg-ink-50/70 px-5 py-3">
            <span v-for="column in columns" :key="column" class="h-3 w-16 rounded bg-ink-200/80" />
        </div>
        <div v-for="row in rows" :key="row" class="flex animate-pulse items-center gap-6 px-5 py-4">
            <span v-for="column in columns" :key="column" class="h-3.5 rounded bg-ink-100" :class="WIDTHS[(row + column) % WIDTHS.length]" />
        </div>
        <span class="sr-only">{{ label }}</span>
    </div>
</template>
