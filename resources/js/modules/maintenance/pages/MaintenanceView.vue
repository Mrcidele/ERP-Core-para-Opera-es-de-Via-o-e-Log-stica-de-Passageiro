<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import ListToolbar from '@/core/ui/ListToolbar.vue';
import PaginationBar from '@/core/ui/PaginationBar.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { formatCents } from '@/core/utils/format';
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
} from '../types/maintenance';

const store = useMaintenanceStore();
const auth = useAuthStore();
const toast = useToast();

// Sem maintenance.manage a tela é só de consulta.
const canManage = computed(() => auth.can('maintenance.manage'));

const openFormVisible = ref(false);
const completing = ref<MaintenanceOrderSummary | null>(null);
const canceling = ref<MaintenanceOrderSummary | null>(null);

onMounted(() => store.fetchOrders());

const STATUS_OPTIONS = MAINTENANCE_STATUSES.map((status) => ({ value: status, label: MAINTENANCE_STATUS_LABELS[status] }));

async function exportCsv(): Promise<void> {
    if (await store.exportCsv()) {
        toast.success('Relatório de ordens de serviço exportado com os filtros da tela.');
    } else {
        toast.error('Não foi possível exportar as ordens de serviço. Tente novamente.');
    }
}

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
        <ListToolbar
            :statuses="STATUS_OPTIONS"
            all-label="Todas"
            :status="store.status"
            :search="store.search"
            search-label="Buscar por placa, mecânico ou serviço"
            @update:status="store.applyFilters({ status: $event })"
            @update:search="store.applyFilters({ search: $event })"
        >
            <button type="button" class="btn btn-quiet !h-9" data-test="export-csv" :disabled="store.exportStatus === 'loading'" @click="exportCsv">
                <AppIcon name="download" />
                {{ store.exportStatus === 'loading' ? 'Exportando…' : 'Exportar CSV' }}
            </button>
        </ListToolbar>

        <TableSkeleton
            v-if="store.listStatus === 'idle' || store.listStatus === 'loading'"
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
            v-else-if="store.orders.length === 0 && !store.filtering"
            data-test="empty"
            icon="wrench"
            title="Nenhuma ordem de serviço"
            :description="canManage ? 'Abra uma ordem quando um veículo precisar ir para a oficina.' : 'Ainda não há ordens de serviço.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="openFormVisible = true">Abrir ordem de serviço</button>
        </EmptyState>

        <EmptyState v-else-if="store.orders.length === 0" data-test="no-results" icon="search" title="Nenhuma ordem corresponde ao filtro">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="store.applyFilters({ search: '', status: null })">Limpar filtros</button>
        </EmptyState>

        <template v-else>
            <div :class="{ 'opacity-60': store.refreshing }" :aria-busy="store.refreshing">
                <MaintenanceOrderTable :orders="store.orders" :can-manage="canManage" @complete="completing = $event" @cancel="askCancel" />
            </div>
            <PaginationBar :meta="store.meta" singular="ordem" plural="ordens" :busy="store.refreshing" @change="store.fetchOrders($event)" />
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
