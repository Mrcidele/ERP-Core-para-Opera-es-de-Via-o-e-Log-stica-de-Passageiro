<script setup lang="ts">
import { computed } from 'vue';
import type { PageMeta } from '@/core/api/pagination';
import { formatInteger } from '@/core/utils/format';

// Rodapé de tabela paginada no servidor: quantos registros estão na tela, de quantos, e a troca de página.
const props = withDefaults(
    defineProps<{
        meta: PageMeta;
        /** Nome do que a tabela lista, para o resumo: "veículo" / "veículos". */
        singular: string;
        plural: string;
        /** Trava os botões enquanto a página nova carrega. */
        busy?: boolean;
    }>(),
    { busy: false },
);

const emit = defineEmits<{ change: [page: number] }>();

const first = computed(() => (props.meta.total === 0 ? 0 : (props.meta.current_page - 1) * props.meta.per_page + 1));
const last = computed(() => Math.min(props.meta.current_page * props.meta.per_page, props.meta.total));

const summary = computed(() => {
    const noun = props.meta.total === 1 ? props.singular : props.plural;

    return props.meta.last_page <= 1
        ? `${formatInteger(props.meta.total)} ${noun}`
        : `${formatInteger(first.value)} a ${formatInteger(last.value)} de ${formatInteger(props.meta.total)} ${noun}`;
});

/** Primeira, última e as vizinhas da atual; `null` marca um trecho omitido. */
const pages = computed<(number | null)[]>(() => {
    const { current_page: current, last_page: total } = props.meta;
    const wanted = [1, current - 1, current, current + 1, total].filter((page) => page >= 1 && page <= total);
    const unique = [...new Set(wanted)].sort((a, b) => a - b);
    const result: (number | null)[] = [];

    unique.forEach((page, index) => {
        const previous = unique[index - 1];

        if (previous !== undefined && page - previous > 1) {
            // Um buraco de uma página só mostra a própria página, não reticências.
            result.push(page - previous === 2 ? page - 1 : null);
        }

        result.push(page);
    });

    return result;
});

function go(page: number): void {
    if (!props.busy && page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
        emit('change', page);
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-200 px-5 py-2.5" data-test="pagination">
        <p class="num text-xs text-ink-500" data-test="pagination-summary" aria-live="polite">{{ summary }}</p>

        <nav v-if="meta.last_page > 1" aria-label="Páginas" class="flex items-center gap-1">
            <button
                type="button"
                class="page-step"
                data-test="page-previous"
                :disabled="busy || meta.current_page <= 1"
                @click="go(meta.current_page - 1)"
            >
                Anterior
            </button>

            <template v-for="(page, index) in pages" :key="page ?? `gap-${index}`">
                <span v-if="page === null" class="num px-1 text-xs text-ink-400" aria-hidden="true">…</span>
                <button
                    v-else
                    type="button"
                    class="page-number num"
                    :data-test="`page-${page}`"
                    :aria-current="page === meta.current_page ? 'page' : undefined"
                    :aria-label="`Página ${page}`"
                    :disabled="busy && page !== meta.current_page"
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </template>

            <button
                type="button"
                class="page-step"
                data-test="page-next"
                :disabled="busy || meta.current_page >= meta.last_page"
                @click="go(meta.current_page + 1)"
            >
                Próxima
            </button>
        </nav>
    </div>
</template>
