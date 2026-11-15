<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { countLabel, formatCents } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
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
const auth = useAuthStore();
const toast = useToast();

// Sem maintenance.manage a tela é só de consulta.
const canManage = computed(() => auth.can('maintenance.manage'));

const statusFilter = ref<MaintenanceStatus | 'all'>('all');
const openFormVisible = ref(false);
const completing = ref<MaintenanceOrderSummary | null>(null);
const canceling = ref<MaintenanceOrderSummary | null>(null);

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
    toast.success('Ordem de serviço aberta. O veículo está em manutenção.');
    openFormVisible.value = false;
}

function onCompleted(order: MaintenanceOrder): void {
    toast.success(`Ordem do veículo ${completing.value?.vehicle.plate ?? ''} concluída por ${formatCents(order.cost_cents)}.`);
    completing.value = null;
}

function askCancel(order: MaintenanceOrderSummary): void {
    store.resetSaveState();
    canceling.value = order;
}

async function confirmCancel(): Promise<void> {
    const order = canceling.value;

    if (order && (await store.cancel(order.id))) {
        toast.success(`Ordem do veículo ${order.vehicle.plate} cancelada.`);
        canceling.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Manutenção</h1>
            <p class="mt-1 text-sm text-ink-500">Ordens de serviço da oficina. Veículo com ordem pendente não entra em viagem.</p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-order" @click="openFormVisible = true">
            <AppIcon name="plus" />
            Abrir ordem de serviço
        </button>
    </header>

    <section class="card mt-6 overflow-hidden">
        <div class="border-b border-ink-200 p-3">
            <div role="group" aria-label="Filtrar por status" class="segmented">
                <button
                    v-for="filter in filters"
                    :key="filter.value"
                    type="button"
                    :data-test="`filter-${filter.value}`"
                    :aria-pressed="statusFilter === filter.value"
                    @click="statusFilter = filter.value"
                >
                    {{ filter.label }}
                    <span class="num ml-1 text-xs text-ink-400">{{ filter.count }}</span>
                </button>
            </div>
        </div>

        <TableSkeleton
            v-if="store.listStatus === 'idle' || (store.listStatus === 'loading' && store.orders.length === 0)"
            data-test="loading"
            :columns="6"
            label="Carregando ordens de serviço"
        />

        <EmptyState
            v-else-if="store.listStatus === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar as ordens de serviço"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchOrders()">Tentar novamente</button>
        </EmptyState>

        <EmptyState
            v-else-if="store.orders.length === 0"
            data-test="empty"
            icon="wrench"
            title="Nenhuma ordem de serviço"
            :description="canManage ? 'Abra uma ordem quando um veículo precisar ir para a oficina.' : 'Ainda não há ordens de serviço.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="openFormVisible = true">Abrir ordem de serviço</button>
        </EmptyState>

        <EmptyState v-else-if="visibleOrders.length === 0" data-test="no-results" icon="search" title="Nenhuma ordem com esse status">
            <button type="button" class="btn btn-quiet" @click="statusFilter = 'all'">Ver todas</button>
        </EmptyState>

        <template v-else>
            <MaintenanceOrderTable :orders="visibleOrders" :can-manage="canManage" @complete="completing = $event" @cancel="askCancel" />
            <p class="border-t border-ink-200 px-5 py-2.5 text-xs text-ink-500">
                <template v-if="statusFilter !== 'all'">{{ visibleOrders.length }} de {{ countLabel(store.orders.length, 'ordem', 'ordens') }}</template>
                <template v-else>{{ countLabel(store.orders.length, 'ordem', 'ordens') }}</template>
            </p>
        </template>
    </section>

    <SlideOver :open="openFormVisible" title="Abrir ordem de serviço" subtitle="O veículo sai da escala até a ordem ser encerrada." @close="openFormVisible = false">
        <OpenMaintenanceOrderForm @opened="onOpened" @cancel="openFormVisible = false" />
    </SlideOver>

    <SlideOver :open="completing !== null" :title="`Concluir ordem do ${completing?.vehicle.plate ?? ''}`" subtitle="Informe o custo final do serviço." @close="completing = null">
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
