<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/components/AppIcon.vue';
import ConfirmDialog from '@/core/components/ConfirmDialog.vue';
import SlideOver from '@/core/components/SlideOver.vue';
import VehicleForm from '../components/VehicleForm.vue';
import VehicleTable from '../components/VehicleTable.vue';
import { useVehicleStore } from '../stores/vehicleStore';
import { VEHICLE_STATUS_LABELS, VEHICLE_STATUSES, type Vehicle, type VehicleStatus } from '../types/vehicle';

const store = useVehicleStore();

const statusFilter = ref<VehicleStatus | 'all'>('all');
const search = ref('');

const formOpen = ref(false);
const editing = ref<Vehicle | null>(null);
const removing = ref<Vehicle | null>(null);
const notice = ref<string | null>(null);

onMounted(() => store.fetchAll());

const filters = computed(() => [
    { value: 'all' as const, label: 'Todos', count: store.vehicles.length },
    ...VEHICLE_STATUSES.map((status) => ({
        value: status,
        label: VEHICLE_STATUS_LABELS[status],
        count: store.vehicles.filter((vehicle) => vehicle.status === status).length,
    })),
]);

const visibleVehicles = computed(() => {
    const term = search.value.replace(/[\s-]/g, '').toUpperCase();

    return store.vehicles.filter(
        (vehicle) =>
            (statusFilter.value === 'all' || vehicle.status === statusFilter.value) &&
            (term === '' || vehicle.plate.includes(term) || vehicle.chassis.includes(term)),
    );
});

const filtering = computed(() => statusFilter.value !== 'all' || search.value.trim() !== '');

function clearFilters(): void {
    statusFilter.value = 'all';
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
    notice.value = editing.value ? `Veículo ${vehicle.plate} atualizado.` : `Veículo ${vehicle.plate} cadastrado.`;
    formOpen.value = false;
}

async function confirmRemove(): Promise<void> {
    const vehicle = removing.value;

    if (vehicle && (await store.remove(vehicle.id))) {
        notice.value = `Veículo ${vehicle.plate} excluído.`;
        removing.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">Frota</h1>
            <p class="mt-1 text-ink-500">Ônibus cadastrados, com classe, lotação, quilometragem e situação.</p>
        </div>
        <button type="button" class="btn btn-primary" data-test="new-vehicle" @click="openCreate">
            <AppIcon name="plus" />
            Cadastrar veículo
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
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 p-4">
            <div role="group" aria-label="Filtrar por status" class="flex flex-wrap gap-1 rounded-lg bg-ink-50 p-1">
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

            <label class="relative block w-full sm:w-72">
                <span class="sr-only">Buscar por placa ou chassi</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-400"><AppIcon name="search" /></span>
                <input
                    v-model="search"
                    type="search"
                    data-test="search"
                    class="field-input !mt-0 !h-10 pl-10 !text-sm"
                    placeholder="Buscar por placa ou chassi"
                />
            </label>
        </div>

        <div v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" class="space-y-3 p-5" aria-busy="true">
            <div v-for="row in 5" :key="row" class="h-12 animate-pulse rounded-md bg-ink-50" />
            <span class="sr-only">Carregando veículos</span>
        </div>

        <div v-else-if="store.listStatus === 'error'" data-test="list-error" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Não foi possível carregar a frota</h2>
            <p class="mt-1 text-ink-500">Verifique a conexão e tente de novo.</p>
            <button type="button" class="btn btn-quiet mt-5" @click="store.fetchAll()">Tentar novamente</button>
        </div>

        <div v-else-if="store.vehicles.length === 0" data-test="empty" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Nenhum veículo cadastrado</h2>
            <p class="mt-1 text-ink-500">Cadastre o primeiro ônibus para começar a montar a frota.</p>
            <button type="button" class="btn btn-primary mt-5" @click="openCreate">Cadastrar veículo</button>
        </div>

        <div v-else-if="visibleVehicles.length === 0" data-test="no-results" class="p-10 text-center">
            <h2 class="font-display text-lg font-semibold">Nenhum veículo corresponde ao filtro</h2>
            <button type="button" class="btn btn-quiet mt-5" @click="clearFilters">Limpar filtros</button>
        </div>

        <template v-else>
            <VehicleTable :vehicles="visibleVehicles" @edit="openEdit" @remove="removing = $event" />
            <p class="border-t border-ink-100 px-5 py-3 text-sm text-ink-500">
                <template v-if="filtering">{{ visibleVehicles.length }} de {{ store.vehicles.length }} veículos</template>
                <template v-else>{{ store.vehicles.length }} veículos</template>
            </p>
        </template>
    </section>

    <SlideOver :open="formOpen" :title="editing ? `Editar ${editing.plate}` : 'Cadastrar veículo'" @close="formOpen = false">
        <VehicleForm :key="editing?.id ?? 'new'" :vehicle="editing" @saved="onSaved" @cancel="formOpen = false" />
    </SlideOver>

    <ConfirmDialog
        :open="removing !== null"
        :title="`Excluir ${removing?.plate ?? ''}?`"
        message="O veículo sai da frota e a exclusão não pode ser desfeita."
        confirm-label="Excluir veículo"
        :busy="store.removeStatus === 'loading'"
        :error="store.removeError"
        @confirm="confirmRemove"
        @cancel="removing = null"
    />
</template>
