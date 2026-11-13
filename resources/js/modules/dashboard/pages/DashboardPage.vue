<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useVehicleStore } from '@/modules/fleet/stores/vehicleStore';
import { VEHICLE_STATUS_LABELS, VEHICLE_STATUSES, type VehicleStatus } from '@/modules/fleet/types/vehicle';

const store = useVehicleStore();

onMounted(() => {
    if (store.listStatus === 'idle') {
        void store.fetchAll();
    }
});

const BAR: Record<VehicleStatus, string> = {
    active: 'bg-road-600',
    maintenance: 'bg-signal-500',
    inactive: 'bg-ink-300',
};

const total = computed(() => store.vehicles.length);

const breakdown = computed(() =>
    VEHICLE_STATUSES.map((status) => {
        const count = store.vehicles.filter((vehicle) => vehicle.status === status).length;

        return { status, count, share: total.value === 0 ? 0 : (count / total.value) * 100 };
    }),
);
</script>

<template>
    <header>
        <h1 class="font-display text-3xl font-semibold tracking-tight">Painel</h1>
        <p class="mt-1 text-ink-500">Situação da frota agora.</p>
    </header>

    <section class="mt-6 max-w-3xl rounded-xl bg-white p-6 shadow-panel">
        <div v-if="store.listStatus === 'error'" data-test="dashboard-error">
            <h2 class="font-display text-lg font-semibold">Não foi possível carregar a frota</h2>
            <button type="button" class="btn btn-quiet mt-4" @click="store.fetchAll()">Tentar novamente</button>
        </div>

        <div v-else-if="store.listStatus !== 'success'" class="h-24 animate-pulse rounded-md bg-ink-50" aria-busy="true" />

        <div v-else-if="total === 0">
            <h2 class="font-display text-lg font-semibold">Nenhum veículo cadastrado</h2>
            <RouterLink :to="{ name: 'fleet' }" class="btn btn-primary mt-4">Cadastrar o primeiro veículo</RouterLink>
        </div>

        <template v-else>
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-lg font-semibold">
                    <span class="tabular-nums">{{ breakdown[0]?.count }}</span> de <span class="tabular-nums">{{ total }}</span> ônibus
                    disponíveis
                </h2>
                <RouterLink :to="{ name: 'fleet' }" class="text-sm font-semibold text-ink-700 underline underline-offset-4 hover:text-ink-900">
                    Ver frota
                </RouterLink>
            </div>

            <div class="mt-5 flex h-3 overflow-hidden rounded-full bg-ink-50" role="img" :aria-label="`${breakdown[0]?.count} de ${total} ônibus ativos`">
                <div v-for="item in breakdown" :key="item.status" :class="BAR[item.status]" :style="{ width: `${item.share}%` }" />
            </div>

            <dl class="mt-5 grid grid-cols-3 gap-4">
                <div v-for="item in breakdown" :key="item.status">
                    <dt class="flex items-center gap-2 text-sm text-ink-500">
                        <span class="h-2 w-2 rounded-full" :class="BAR[item.status]" />
                        {{ VEHICLE_STATUS_LABELS[item.status] }}
                    </dt>
                    <dd class="mt-1 font-display text-2xl font-semibold tabular-nums">{{ item.count }}</dd>
                </div>
            </dl>
        </template>
    </section>
</template>
