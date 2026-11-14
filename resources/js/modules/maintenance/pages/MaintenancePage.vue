<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/components/AppIcon.vue';
import ConfirmDialog from '@/core/components/ConfirmDialog.vue';
import SlideOver from '@/core/components/SlideOver.vue';
import { countLabel, formatCents } from '@/core/utils/format';
import CompleteMaintenanceOrderForm from '../components/CompleteMaintenanceOrderForm.vue';
import MaintenanceOrderTable from '../components/MaintenanceOrderTable.vue';
import OpenMaintenanceOrderForm from '../components/OpenMaintenanceOrderForm.vue';
import { useMaintenanceStore } from '../stores/maintenanceStore';
import {
    MAINTENANCE_STATUS_LABELS,
    MAINTENANCE_STATUSES,
    type MaintenanceOrder,
    type MaintenanceOrderSummary,
    type MaintenanceStatus,
} from '../types/maintenance';

const store = useMaintenanceStore();

const statusFilter = ref<MaintenanceStatus | 'all'>('all');
const openFormVisible = ref(false);
const completing = ref<MaintenanceOrderSummary | null>(null);
const canceling = ref<MaintenanceOrderSummary | null>(null);
const notice = ref<string | null>(null);

onMounted(() => store.fetchOrders());

// "Em andamento" só entra no filtro quando existe alguma ordem nesse status.
const filters = computed(() => [
    { value: 'all' as const, label: 'Todas', count: store.orders.length },
    ...MAINTENANCE_STATUSES.map((status) => ({
        value: status,
        label: MAINTENANCE_STATUS_LABELS[status],
        count: store.orders.filter((order) => order.status === status).length,
    })).filter((filter) => filter.value !== 'in_progress' || filter.count > 0),
]);

const visibleOrders = computed(() =>
    statusFilter.value === 'all' ? store.orders : store.orders.filter((order) => order.status === statusFilter.value),
);

function onOpened(): void {
    notice.value = 'Ordem de serviço aberta. O veículo está em manutenção.';
    openFormVisible.value = false;
}

function onCompleted(order: MaintenanceOrder): void {
    notice.value = `Ordem do veículo ${completing.value?.vehicle.plate ?? ''} concluída por ${formatCents(order.cost_cents)}.`;
    completing.value = null;
}

function askCancel(order: MaintenanceOrderSummary): void {
    store.resetSaveState();
    canceling.value = order;
}

async function confirmCancel(): Promise<void> {
    const order = canceling.value;

    if (order && (await store.cancel(order.id))) {
        notice.value = `Ordem do veículo ${order.vehicle.plate} cancelada.`;
        canceling.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">Manutenção</h1>
            <p class="mt-1 text-ink-500">Ordens de serviço da oficina. Veículo com ordem pendente não entra em viagem.</p>
        </div>
        <button type="button" class="btn btn-primary" data-test="new-order" @click="openFormVisible = true">
            <AppIcon name="plus" />
            Abrir ordem de serviço
        </button>
    </header>

    <p
        v-if="notice"
        role="status"
        data-test="notice"
        class="mt-6 flex items-center justify-between gap-4 rounded-md bg-road-100 px-4 py-3 text-sm font-medium text-road-800"
    >
        {{ notice }}
        <button type="button" class="underline underline-offset-2" @click="notice = null">Dispensar</button>
    </p>

    <section class="mt-6 rounded-xl bg-white shadow-panel">
        <div class="border-b border-ink-100 p-4">
            <div role="group" aria-label="Filtrar por status" class="inline-flex flex-wrap gap-1 rounded-lg bg-ink-50 p-1">
                <button
                    v-for="filter in filters"
                    :key="filter.value"
                    type="button"
                    :data-test="`filter-${filter.value}`"
                    :aria-pressed="statusFilter === filter.value"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="statusFilter === filter.value ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-900'"
                    @click="statusFilter = filter.value"
                >
                    {{ filter.label }}
                    <span class="ml-1 tabular-nums text-ink-400">{{ filter.count }}</span>
                </button>
            </div>
        </div>

        <div v-if="store.listStatus === 'idle' || (store.listStatus === 'loading' && store.orders.length === 0)" data-test="loading" class="space-y-3 p-5" aria-busy="true">
            <div v-for="row in 5" :key="row" class="h-12 animate-pulse rounded-md bg-ink-50" />
            <span class="sr-only">Carregando ordens de serviço</span>
        </div>

        <div v-else-if="store.listStatus === 'error'" data-test="list-error" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Não foi possível carregar as ordens de serviço</h2>
            <p class="mt-1 text-ink-500">Verifique a conexão e tente de novo.</p>
            <button type="button" class="btn btn-quiet mt-5" @click="store.fetchOrders()">Tentar novamente</button>
        </div>

        <div v-else-if="store.orders.length === 0" data-test="empty" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Nenhuma ordem de serviço</h2>
            <p class="mt-1 text-ink-500">Abra uma ordem quando um veículo precisar ir para a oficina.</p>
            <button type="button" class="btn btn-primary mt-5" @click="openFormVisible = true">Abrir ordem de serviço</button>
        </div>

        <div v-else-if="visibleOrders.length === 0" data-test="no-results" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Nenhuma ordem com esse status</h2>
            <button type="button" class="btn btn-quiet mt-5" @click="statusFilter = 'all'">Ver todas</button>
        </div>

        <template v-else>
            <MaintenanceOrderTable :orders="visibleOrders" @complete="completing = $event" @cancel="askCancel" />
            <p class="border-t border-ink-100 px-5 py-3 text-sm text-ink-500">
                <template v-if="statusFilter !== 'all'">{{ visibleOrders.length }} de {{ countLabel(store.orders.length, 'ordem', 'ordens') }}</template>
                <template v-else>{{ countLabel(store.orders.length, 'ordem', 'ordens') }}</template>
            </p>
        </template>
    </section>

    <SlideOver :open="openFormVisible" title="Abrir ordem de serviço" @close="openFormVisible = false">
        <OpenMaintenanceOrderForm @opened="onOpened" @cancel="openFormVisible = false" />
    </SlideOver>

    <SlideOver :open="completing !== null" :title="`Concluir ordem do ${completing?.vehicle.plate ?? ''}`" @close="completing = null">
        <CompleteMaintenanceOrderForm v-if="completing" :key="completing.id" :order="completing" @completed="onCompleted" @cancel="completing = null" />
    </SlideOver>

    <ConfirmDialog
        :open="canceling !== null"
        :title="`Cancelar a ordem do ${canceling?.vehicle.plate ?? ''}?`"
        message="A ordem é encerrada sem custo. Se não houver outra pendente, o veículo volta a ficar ativo."
        confirm-label="Cancelar ordem"
        dismiss-label="Voltar"
        :busy="store.saveStatus === 'loading'"
        :error="store.saveError"
        @confirm="confirmCancel"
        @cancel="canceling = null"
    />
</template>
