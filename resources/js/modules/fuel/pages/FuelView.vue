<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import ListToolbar from '@/core/ui/ListToolbar.vue';
import PaginationBar from '@/core/ui/PaginationBar.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import StatCard from '@/core/ui/StatCard.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { formatCents, formatDateTime, formatKmPerLiter, formatLiters, formatMetersPerLiter } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import FuelForm from '../components/FuelForm.vue';
import FuelLogTable from '../components/FuelLogTable.vue';
import { useFuelStore } from '../stores/fuelStore';
import type { FuelLog } from '../types/fuelLog';

const store = useFuelStore();
const auth = useAuthStore();
const toast = useToast();

// O abastecimento é parte da gestão da frota: sem fleet.manage a tela é só de consulta.
const canManage = computed(() => auth.can('fleet.manage'));

const formOpen = ref(false);
const removing = ref<FuelLog | null>(null);

onMounted(() => Promise.all([store.fetchLogs(), store.fetchSummary()]));

const month = new Intl.DateTimeFormat('pt-BR', { month: 'long', timeZone: 'UTC' });
const summary = computed(() => store.monthSummary);
const monthName = computed(() => (summary.value ? month.format(new Date(summary.value.month_start)) : ''));

function askRemove(log: FuelLog): void {
    store.resetRemoveState();
    removing.value = log;
}

async function confirmRemove(): Promise<void> {
    const log = removing.value;

    if (log && (await store.remove(log.id))) {
        toast.success(`Abastecimento de ${log.vehicle.plate} em ${formatDateTime(log.fueled_at)} excluído.`);
        removing.value = null;
    }
}

function onRecorded(log: FuelLog): void {
    const summary = `${formatLiters(log.liters_milliliters)} no veículo ${log.vehicle.plate}`;

    if (log.low_efficiency_alert && log.km_per_liter !== null) {
        toast.warning(`${summary}, com rendimento de ${formatKmPerLiter(log.km_per_liter)}. Abaixo da meta de ${formatMetersPerLiter(log.min_meters_per_liter)} deste veículo.`, 'Consumo alto');
    } else {
        toast.success(`Abastecimento registrado: ${summary}${log.km_per_liter === null ? '' : `, ${formatKmPerLiter(log.km_per_liter)}`}.`);
    }

    formOpen.value = false;
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Abastecimento</h1>
            <p class="mt-1 text-sm text-ink-500">
                Diesel por veículo, com o rendimento de cada trecho. Abaixo da meta de km/L do caminhão, o abastecimento aparece como consumo alto.
            </p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-fuel-log" @click="formOpen = true">
            <AppIcon name="plus" />
            Registrar abastecimento
        </button>
    </header>

    <section v-if="summary" class="mt-6 grid gap-4 sm:grid-cols-3" aria-label="Resumo do mês" data-test="month-summary">
        <StatCard data-test="summary-cost" :label="`Gasto em ${monthName}`" :value="formatCents(summary.month_cost_cents)" icon="coins" />
        <StatCard data-test="summary-liters" :label="`Volume em ${monthName}`" :value="formatLiters(summary.month_liters_milliliters)" icon="fuel" />
        <StatCard data-test="summary-logs" :label="`Abastecimentos em ${monthName}`" :value="String(summary.month_logs)" icon="calendar" />
    </section>

    <section class="card mt-6 overflow-hidden">
        <ListToolbar :search="store.search" search-label="Buscar por placa ou posto" @update:search="store.applyFilters({ search: $event })" />

        <TableSkeleton v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" :columns="7" label="Carregando abastecimentos" />

        <EmptyState
            v-else-if="store.listStatus === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar os abastecimentos"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchLogs()">Tentar novamente</button>
        </EmptyState>

        <EmptyState
            v-else-if="store.logs.length === 0 && !store.filtering"
            data-test="empty"
            icon="fuel"
            title="Nenhum abastecimento registrado"
            :description="canManage ? 'Registre o primeiro. A partir do segundo de cada veículo, o rendimento em km/L aparece aqui.' : 'Ainda não há abastecimentos registrados.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="formOpen = true">Registrar abastecimento</button>
        </EmptyState>

        <EmptyState v-else-if="store.logs.length === 0" data-test="no-results" icon="search" title="Nenhum abastecimento corresponde à busca">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="store.applyFilters({ search: '' })">Limpar busca</button>
        </EmptyState>

        <template v-else>
            <div :class="{ 'opacity-60': store.refreshing }" :aria-busy="store.refreshing">
                <FuelLogTable :logs="store.logs" :can-manage="canManage" @remove="askRemove" />
            </div>
            <PaginationBar :meta="store.meta" singular="abastecimento" plural="abastecimentos" :busy="store.refreshing" @change="store.fetchLogs($event)" />
        </template>
    </section>

    <ConfirmDialog
        :open="removing !== null"
        :title="removing ? `Excluir o abastecimento de ${removing.vehicle.plate}?` : ''"
        message="O registro sai da lista e do rendimento. O abastecimento seguinte deste veículo passa a ser comparado com o anterior a este, e um lançamento com o hodômetro correto pode ser feito em seguida."
        confirm-label="Excluir abastecimento"
        :busy="store.removeStatus === 'loading'"
        :error="store.removeError"
        @confirm="confirmRemove"
        @cancel="removing = null"
    />

    <SlideOver :open="formOpen" title="Registrar abastecimento" subtitle="O rendimento é calculado em relação ao abastecimento anterior do veículo." @close="formOpen = false">
        <FuelForm @recorded="onRecorded" @cancel="formOpen = false" />
    </SlideOver>
</template>
