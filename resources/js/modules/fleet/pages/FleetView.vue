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
import { useAuthStore } from '@/modules/auth/stores/authStore';
import VehicleForm from '../components/VehicleForm.vue';
import VehicleTable from '../components/VehicleTable.vue';
import { useVehicleStore } from '../stores/vehicleStore';
import { VEHICLE_STATUS_LABELS, VEHICLE_STATUSES, type Vehicle } from '../types/vehicle';

const store = useVehicleStore();
const auth = useAuthStore();
const toast = useToast();

// Sem fleet.manage a tela é só de consulta.
const canManage = computed(() => auth.can('fleet.manage'));

const formOpen = ref(false);
const editing = ref<Vehicle | null>(null);
const removing = ref<Vehicle | null>(null);

onMounted(() => store.fetchAll());

const STATUS_OPTIONS = VEHICLE_STATUSES.map((status) => ({ value: status, label: VEHICLE_STATUS_LABELS[status] }));

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(vehicle: Vehicle): void {
    editing.value = vehicle;
    formOpen.value = true;
}

function onSaved(vehicle: Vehicle): void {
    toast.success(editing.value ? `Veículo ${vehicle.plate} atualizado.` : `Veículo ${vehicle.plate} cadastrado.`);
    formOpen.value = false;
}

async function confirmRemove(): Promise<void> {
    const vehicle = removing.value;

    if (vehicle && (await store.remove(vehicle.id))) {
        toast.success(`Veículo ${vehicle.plate} excluído.`);
        removing.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Frota</h1>
            <p class="mt-1 text-sm text-ink-500">Caminhões e utilitários, com tipo, carga útil, meta de km/L, quilometragem, revisão preventiva e situação.</p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-vehicle" @click="openCreate">
            <AppIcon name="plus" />
            Cadastrar veículo
        </button>
    </header>

    <section class="card mt-6 overflow-hidden">
        <ListToolbar
            :statuses="STATUS_OPTIONS"
            :status="store.status"
            :search="store.search"
            search-label="Buscar por placa, chassi ou modelo"
            @update:status="store.applyFilters({ status: $event })"
            @update:search="store.applyFilters({ search: $event })"
        >
            <button
                type="button"
                data-test="filter-due"
                :aria-pressed="store.onlyDue"
                class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors"
                :class="store.onlyDue ? 'border-signal-500 bg-signal-50 text-signal-700' : 'border-ink-200 bg-white text-ink-600 hover:border-ink-300 hover:text-ink-900'"
                @click="store.showOnlyDue(!store.onlyDue)"
            >
                <AppIcon name="alert" />
                Revisão vencida
            </button>
        </ListToolbar>

        <TableSkeleton v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" :columns="7" label="Carregando veículos" />

        <EmptyState
            v-else-if="store.listStatus === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar a frota"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchAll()">Tentar novamente</button>
        </EmptyState>

        <EmptyState
            v-else-if="store.vehicles.length === 0 && !store.filtering"
            data-test="empty"
            icon="truck"
            title="Nenhum veículo cadastrado"
            :description="canManage ? 'Cadastre o primeiro caminhão para começar a montar a frota.' : 'Ainda não há caminhões na frota.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="openCreate">Cadastrar veículo</button>
        </EmptyState>

        <EmptyState v-else-if="store.vehicles.length === 0" data-test="no-results" icon="search" title="Nenhum veículo corresponde ao filtro">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="store.applyFilters({ search: '', status: null, extra: {} })">Limpar filtros</button>
        </EmptyState>

        <template v-else>
            <div :class="{ 'opacity-60': store.refreshing }" :aria-busy="store.refreshing">
                <VehicleTable :vehicles="store.vehicles" :can-manage="canManage" @edit="openEdit" @remove="removing = $event" />
            </div>
            <PaginationBar :meta="store.meta" singular="veículo" plural="veículos" :busy="store.refreshing" @change="store.fetchAll($event)" />
        </template>
    </section>

    <SlideOver
        :open="formOpen"
        :title="editing ? `Editar ${editing.plate}` : 'Cadastrar veículo'"
        :subtitle="editing ? 'Altere os dados e a situação do veículo.' : 'O veículo entra na frota como ativo.'"
        @close="formOpen = false"
    >
        <VehicleForm :key="editing?.id ?? 'new'" :vehicle="editing" @saved="onSaved" @cancel="formOpen = false" />
    </SlideOver>

    <ConfirmDialog
        :open="removing !== null"
        :title="`Excluir ${removing?.plate ?? ''}?`"
        message="O veículo sai da frota. O histórico dele é mantido."
        confirm-label="Excluir veículo"
        :busy="store.removeStatus === 'loading'"
        :error="store.removeError"
        @confirm="confirmRemove"
        @cancel="removing = null"
    />
</template>
