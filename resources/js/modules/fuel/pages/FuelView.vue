<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import ListToolbar from '@/core/ui/ListToolbar.vue';
import PaginationBar from '@/core/ui/PaginationBar.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { formatKmPerLiter, formatLiters } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import FuelForm from '../components/FuelForm.vue';
import FuelLogTable from '../components/FuelLogTable.vue';
import { useFuelStore } from '../stores/fuelStore';
import { LOW_EFFICIENCY_KM_PER_LITER, type FuelLog } from '../types/fuelLog';

const store = useFuelStore();
const auth = useAuthStore();
const toast = useToast();

// O abastecimento é parte da gestão da frota: sem fleet.manage a tela é só de consulta.
const canManage = computed(() => auth.can('fleet.manage'));

const formOpen = ref(false);

onMounted(() => store.fetchLogs());

function onRecorded(log: FuelLog): void {
    const summary = `${formatLiters(log.liters_milliliters)} no veículo ${log.vehicle.plate}`;

    if (log.low_efficiency_alert && log.km_per_liter !== null) {
        toast.warning(`${summary}, com rendimento de ${formatKmPerLiter(log.km_per_liter)}. Abaixo do padrão de ${formatKmPerLiter(LOW_EFFICIENCY_KM_PER_LITER)}.`, 'Consumo alto');
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
                Combustível por veículo, com o rendimento de cada trecho. Abaixo de <span class="num">{{ formatKmPerLiter(LOW_EFFICIENCY_KM_PER_LITER) }}</span> o consumo está fora do padrão.
            </p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-fuel-log" @click="formOpen = true">
            <AppIcon name="plus" />
            Registrar abastecimento
        </button>
    </header>

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
                <FuelLogTable :logs="store.logs" />
            </div>
            <PaginationBar :meta="store.meta" singular="abastecimento" plural="abastecimentos" :busy="store.refreshing" @change="store.fetchLogs($event)" />
        </template>
    </section>

    <SlideOver :open="formOpen" title="Registrar abastecimento" subtitle="O rendimento é calculado em relação ao abastecimento anterior do veículo." @close="formOpen = false">
        <FuelForm @recorded="onRecorded" @cancel="formOpen = false" />
    </SlideOver>
</template>
