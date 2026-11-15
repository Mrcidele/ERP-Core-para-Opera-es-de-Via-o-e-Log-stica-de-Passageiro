<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { countLabel } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import VehicleForm from '../components/VehicleForm.vue';
import VehicleTable from '../components/VehicleTable.vue';
import { useVehicleStore } from '../stores/vehicleStore';
import { VEHICLE_STATUS_LABELS, VEHICLE_STATUSES, type Vehicle, type VehicleStatus } from '../types/vehicle';

const store = useVehicleStore();
const auth = useAuthStore();
const toast = useToast();

// Sem fleet.manage a tela é só de consulta.
const canManage = computed(() => auth.can('fleet.manage'));

const statusFilter = ref<VehicleStatus | 'all'>('all');
const onlyDue = ref(false);
const search = ref('');

const formOpen = ref(false);
const editing = ref<Vehicle | null>(null);
const removing = ref<Vehicle | null>(null);

onMounted(() => store.fetchAll());

const filters = computed(() => [
    { value: 'all' as const, label: 'Todos', count: store.vehicles.length },
    ...VEHICLE_STATUSES.map((status) => ({
        value: status,
        label: VEHICLE_STATUS_LABELS[status],
        count: store.vehicles.filter((vehicle) => vehicle.status === status).length,
    })),
]);

const dueCount = computed(() => store.vehicles.filter((vehicle) => vehicle.needs_preventive_maintenance).length);

const visibleVehicles = computed(() => {
    const term = search.value.replace(/[\s-]/g, '').toUpperCase();

    return store.vehicles.filter(
        (vehicle) =>
            (statusFilter.value === 'all' || vehicle.status === statusFilter.value) &&
            (!onlyDue.value || vehicle.needs_preventive_maintenance) &&
            (term === '' || vehicle.plate.includes(term) || vehicle.chassis.includes(term)),
    );
});

const filtering = computed(() => statusFilter.value !== 'all' || onlyDue.value || search.value.trim() !== '');

function clearFilters(): void {
    statusFilter.value = 'all';
    onlyDue.value = false;
    search.value = '';
}

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
            <p class="mt-1 text-sm text-ink-500">Ônibus cadastrados, com quilometragem, revisão preventiva e situação.</p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-vehicle" @click="openCreate">
            <AppIcon name="plus" />
            Cadastrar veículo
        </button>
    </header>

    <section class="card mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-ink-200 p-3">
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

            <button
                v-if="dueCount > 0"
                type="button"
                data-test="filter-due"
                :aria-pressed="onlyDue"
                class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors"
                :class="onlyDue ? 'border-signal-500 bg-signal-50 text-signal-700' : 'border-ink-200 bg-white text-ink-600 hover:border-ink-300 hover:text-ink-900'"
                @click="onlyDue = !onlyDue"
            >
                <AppIcon name="alert" />
                Revisão vencida
                <span class="num text-xs">{{ dueCount }}</span>
            </button>

            <label class="relative ml-auto block w-full sm:w-72">
                <span class="sr-only">Buscar por placa ou chassi</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-400"><AppIcon name="search" /></span>
                <input v-model="search" type="search" data-test="search" class="field-input !mt-0 !h-9 pl-10" placeholder="Buscar por placa ou chassi" />
            </label>
        </div>

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
            v-else-if="store.vehicles.length === 0"
            data-test="empty"
            icon="bus"
            title="Nenhum veículo cadastrado"
            :description="canManage ? 'Cadastre o primeiro ônibus para começar a montar a frota.' : 'Ainda não há ônibus na frota.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="openCreate">Cadastrar veículo</button>
        </EmptyState>

        <EmptyState v-else-if="visibleVehicles.length === 0" data-test="no-results" icon="search" title="Nenhum veículo corresponde ao filtro">
            <button type="button" class="btn btn-quiet" @click="clearFilters">Limpar filtros</button>
        </EmptyState>

        <template v-else>
            <VehicleTable :vehicles="visibleVehicles" :can-manage="canManage" @edit="openEdit" @remove="removing = $event" />
            <p class="border-t border-ink-200 px-5 py-2.5 text-xs text-ink-500">
                <template v-if="filtering">{{ visibleVehicles.length }} de {{ countLabel(store.vehicles.length, 'veículo', 'veículos') }}</template>
                <template v-else>{{ countLabel(store.vehicles.length, 'veículo', 'veículos') }}</template>
            </p>
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
