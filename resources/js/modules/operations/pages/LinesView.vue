<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import SearchField from '@/core/ui/SearchField.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { countLabel } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import LineForm from '../components/LineForm.vue';
import LineTable from '../components/LineTable.vue';
import { useLineStore } from '../stores/lineStore';
import type { Line } from '../types/trip';

const store = useLineStore();
const auth = useAuthStore();
const toast = useToast();

// Sem operations.manage a tela é só de consulta.
const canManage = computed(() => auth.can('operations.manage'));

const search = ref('');
const formOpen = ref(false);
const editing = ref<Line | null>(null);
const removing = ref<Line | null>(null);

onMounted(() => store.fetchAll());

const name = (line: Line): string => `${line.origin} → ${line.destination}`;

// A relação de linhas vem inteira da API; a busca por cidade (origem, destino ou parada) é feita aqui.
const visibleLines = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('pt-BR');

    return term === ''
        ? store.lines
        : store.lines.filter((line) =>
              [line.origin, line.destination, ...line.stops.map((stop) => stop.city_name)].some((city) => city.toLocaleLowerCase('pt-BR').includes(term)),
          );
});

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(line: Line): void {
    editing.value = line;
    formOpen.value = true;
}

function askRemove(line: Line): void {
    store.resetRemoveState();
    removing.value = line;
}

function onSaved(line: Line): void {
    toast.success(editing.value ? `Linha ${name(line)} atualizada.` : `Linha ${name(line)} cadastrada.`);
    formOpen.value = false;
}

async function confirmRemove(): Promise<void> {
    const line = removing.value;

    if (line && (await store.remove(line.id))) {
        toast.success(`Linha ${name(line)} excluída.`);
        removing.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Linhas e rotas</h1>
            <p class="mt-1 text-sm text-ink-500">Trajetos da operação, com as paradas no caminho, a distância e a duração.</p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-line" @click="openCreate">
            <AppIcon name="plus" />
            Cadastrar linha
        </button>
    </header>

    <section class="card mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-ink-200 p-3">
            <SearchField v-model="search" class="w-full sm:w-80" label="Buscar por cidade de origem, destino ou parada" :delay="150" />
        </div>

        <TableSkeleton v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" :columns="5" label="Carregando linhas" />

        <EmptyState
            v-else-if="store.listStatus === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar as linhas"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchAll()">Tentar novamente</button>
        </EmptyState>

        <EmptyState
            v-else-if="store.lines.length === 0"
            data-test="empty"
            icon="signpost"
            title="Nenhuma linha cadastrada"
            :description="canManage ? 'Cadastre a primeira linha para poder agendar viagens.' : 'Ainda não há linhas cadastradas.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="openCreate">Cadastrar linha</button>
        </EmptyState>

        <EmptyState v-else-if="visibleLines.length === 0" data-test="no-results" icon="search" title="Nenhuma linha passa por essa cidade">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="search = ''">Limpar busca</button>
        </EmptyState>

        <template v-else>
            <LineTable :lines="visibleLines" :can-manage="canManage" @edit="openEdit" @remove="askRemove" />
            <p class="num border-t border-ink-200 px-5 py-2.5 text-xs text-ink-500" data-test="count">
                <template v-if="search.trim() !== ''">{{ visibleLines.length }} de {{ countLabel(store.lines.length, 'linha', 'linhas') }}</template>
                <template v-else>{{ countLabel(store.lines.length, 'linha', 'linhas') }}</template>
            </p>
        </template>
    </section>

    <SlideOver
        :open="formOpen"
        size="lg"
        :title="editing ? `Editar ${name(editing)}` : 'Cadastrar linha'"
        :subtitle="editing ? 'As viagens já agendadas passam a usar os novos dados.' : 'Informe o trajeto e as paradas no caminho.'"
        @close="formOpen = false"
    >
        <LineForm :key="editing?.id ?? 'new'" :line="editing" @saved="onSaved" @cancel="formOpen = false" />
    </SlideOver>

    <ConfirmDialog
        :open="removing !== null"
        :title="removing ? `Excluir ${name(removing)}?` : ''"
        message="A linha deixa de aparecer para novos agendamentos. As viagens já realizadas continuam no histórico."
        confirm-label="Excluir linha"
        :busy="store.removeStatus === 'loading'"
        :error="store.removeError"
        @confirm="confirmRemove"
        @cancel="removing = null"
    />
</template>
