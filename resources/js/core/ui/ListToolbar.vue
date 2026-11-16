<script setup lang="ts">
import SearchField from './SearchField.vue';

// Barra acima de uma tabela paginada: filtro por status em segmentos, busca e ações (ex.: exportar).
// Os filtros rodam no servidor; a barra só informa o que o usuário escolheu.
withDefaults(
    defineProps<{
        /** Status oferecidos no filtro; sem eles a barra mostra só a busca. */
        statuses?: readonly { value: string; label: string }[];
        status?: string | null;
        /** Rótulo do segmento que remove o filtro: "Todos" / "Todas". */
        allLabel?: string;
        search: string;
        searchLabel: string;
    }>(),
    { statuses: () => [], status: null, allLabel: 'Todos' },
);

const emit = defineEmits<{ 'update:status': [status: string | null]; 'update:search': [search: string] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-3 border-b border-ink-200 p-3">
        <div v-if="statuses.length > 0" role="group" aria-label="Filtrar por status" class="segmented">
            <button type="button" data-test="filter-all" :aria-pressed="status === null" @click="emit('update:status', null)">{{ allLabel }}</button>
            <button
                v-for="option in statuses"
                :key="option.value"
                type="button"
                :data-test="`filter-${option.value}`"
                :aria-pressed="status === option.value"
                @click="emit('update:status', option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <div class="ml-auto flex w-full flex-wrap items-center gap-3 sm:w-auto">
            <SearchField class="w-full sm:w-72" :model-value="search" :label="searchLabel" @update:model-value="emit('update:search', $event)" />
            <slot />
        </div>
    </div>
</template>
