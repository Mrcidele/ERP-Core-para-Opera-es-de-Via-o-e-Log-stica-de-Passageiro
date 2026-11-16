<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '@/core/ui/AppIcon.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import MercosulPlate from '@/core/ui/MercosulPlate.vue';
import StatCard from '@/core/ui/StatCard.vue';
import { countLabel, formatCents, formatDate, formatDateTime, formatKm, formatLiters, formatPercent } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import { useDashboardStore } from '../stores/dashboardStore';

const store = useDashboardStore();
const auth = useAuthStore();

onMounted(() => store.fetchSummary());

const fleet = computed(() => store.summary?.fleet ?? null);
const operations = computed(() => store.summary?.operations ?? null);
const crew = computed(() => store.summary?.crew ?? null);
const maintenance = computed(() => store.summary?.maintenance ?? null);
const fuel = computed(() => store.summary?.fuel ?? null);

/**
 * Os indicadores variam com as permissões. Até quatro, ficam em uma linha; com os cinco, três em cima
 * e os dois de valores em reais embaixo, mais largos, para a linha de baixo não ficar com um cartão só.
 */
const kpiLayout = computed(() => {
    const count = (fleet.value ? 1 : 0) + (operations.value ? 2 : 0) + (maintenance.value ? 1 : 0) + (fuel.value ? 1 : 0);

    return count === 5 ? 'xl:grid-cols-6 xl:[&>*:nth-child(-n+3)]:col-span-2 xl:[&>*:nth-child(n+4)]:col-span-3' : 'xl:grid-cols-4';
});

const firstName = computed(() => auth.user?.name.split(' ')[0] ?? '');

const distribution = computed(() => {
    const data = fleet.value;

    if (data === null || data.total === 0) {
        return [];
    }

    return [
        { key: 'active', label: 'Ativos', count: data.active, bar: 'bg-road-500' },
        { key: 'maintenance', label: 'Em manutenção', count: data.maintenance, bar: 'bg-signal-500' },
        { key: 'inactive', label: 'Inativos', count: data.inactive, bar: 'bg-ink-300' },
    ].map((item) => ({ ...item, share: (item.count / data.total) * 100 }));
});

const pendingOrders = computed(() => (maintenance.value === null ? 0 : maintenance.value.open + maintenance.value.in_progress));

function licenseLabel(days: number): string {
    if (days < 0) {
        return `Vencida há ${countLabel(-days, 'dia', 'dias')}`;
    }

    return days === 0 ? 'Vence hoje' : `Vence em ${countLabel(days, 'dia', 'dias')}`;
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">
                <template v-if="firstName">Olá, {{ firstName }}</template>
                <template v-else>Painel</template>
            </h1>
            <p class="mt-1 text-sm text-ink-500">Situação da operação agora.</p>
        </div>
        <p v-if="store.summary" class="num text-xs text-ink-500" data-test="generated-at">Atualizado em {{ formatDateTime(store.summary.generated_at) }}</p>
    </header>

    <div v-if="store.status === 'idle' || (store.status === 'loading' && store.summary === null)" data-test="loading" class="mt-6" aria-busy="true">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in 4" :key="card" class="card h-[132px] animate-pulse p-5">
                <div class="h-3 w-24 rounded bg-ink-100" />
                <div class="mt-5 h-7 w-20 rounded bg-ink-100" />
                <div class="mt-4 h-3 w-32 rounded bg-ink-100" />
            </div>
        </div>
        <div class="card mt-4 h-40 animate-pulse" />
        <span class="sr-only">Carregando o painel</span>
    </div>

    <section v-else-if="store.status === 'error' && store.summary === null" class="card mt-6">
        <EmptyState data-test="dashboard-error" icon="alert" title="Não foi possível carregar o painel" description="Verifique a conexão e tente de novo.">
            <button type="button" class="btn btn-quiet" @click="store.fetchSummary()">Tentar novamente</button>
        </EmptyState>
    </section>

    <template v-else-if="store.summary">
        <!-- Indicadores: só aparecem os dos módulos que o usuário pode consultar. -->
        <section class="mt-6 grid gap-4 sm:grid-cols-2" :class="kpiLayout" aria-label="Indicadores">
            <StatCard
                v-if="fleet"
                data-test="kpi-availability"
                label="Disponibilidade da frota"
                :value="formatPercent(fleet.availability_rate)"
                :subtitle="`${fleet.active} de ${countLabel(fleet.total, 'ônibus ativo', 'ônibus ativos')}`"
                icon="gauge"
                :tone="fleet.total > 0 && fleet.availability_rate < 70 ? 'attention' : 'positive'"
            />
            <StatCard
                v-if="operations"
                data-test="kpi-in-progress"
                label="Em viagem agora"
                :value="String(operations.in_progress_now)"
                :subtitle="operations.in_progress_now === 1 ? 'viagem em andamento' : 'viagens em andamento'"
                icon="route"
            />
            <StatCard
                v-if="operations"
                data-test="kpi-next-24h"
                label="Saídas em 24 horas"
                :value="String(operations.scheduled_next_24h)"
                :subtitle="operations.scheduled_next_24h === 1 ? 'viagem agendada' : 'viagens agendadas'"
                icon="clock"
            />
            <StatCard
                v-if="maintenance"
                data-test="kpi-maintenance-cost"
                label="Oficina em 30 dias"
                :value="formatCents(maintenance.completed_last_30_days.total_cost_cents)"
                :subtitle="countLabel(maintenance.completed_last_30_days.count, 'ordem concluída', 'ordens concluídas')"
                icon="coins"
                v-bind="pendingOrders > 0 ? { alert: countLabel(pendingOrders, 'pendente', 'pendentes') } : {}"
            />
            <StatCard
                v-if="fuel"
                data-test="kpi-fuel-cost"
                label="Combustível no mês"
                :value="formatCents(fuel.month_cost_cents)"
                :subtitle="`${formatLiters(fuel.month_liters_milliliters)} em ${countLabel(fuel.month_logs, 'abastecimento', 'abastecimentos')}`"
                icon="fuel"
            />
        </section>

        <!-- Distribuição da frota -->
        <section v-if="fleet" class="card mt-4 p-5" data-test="fleet-distribution" aria-label="Distribuição da frota">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="font-display text-base font-semibold text-ink-900">Distribuição da frota</h2>
                <RouterLink :to="{ name: 'fleet' }" class="text-sm font-medium text-ink-600 underline decoration-ink-300 underline-offset-4 hover:text-ink-950">
                    Abrir frota
                </RouterLink>
            </div>

            <template v-if="fleet.total > 0">
                <div class="mt-4 flex h-3 gap-0.5 overflow-hidden rounded-full bg-ink-100" role="img" :aria-label="`${fleet.active} ativos, ${fleet.maintenance} em manutenção e ${fleet.inactive} inativos`">
                    <div v-for="item in distribution" :key="item.key" :class="item.bar" :style="{ width: `${item.share}%` }" :data-test="`bar-${item.key}`" />
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-4">
                    <div v-for="item in distribution" :key="item.key" :data-test="`fleet-${item.key}`">
                        <dt class="flex items-center gap-2 text-sm text-ink-500">
                            <span class="h-2 w-2 rounded-full" :class="item.bar" aria-hidden="true" />
                            {{ item.label }}
                        </dt>
                        <dd class="num mt-1 text-2xl font-semibold text-ink-950">
                            {{ item.count }}
                            <span class="text-sm font-normal text-ink-400">{{ formatPercent(Math.round(item.share * 10) / 10) }}</span>
                        </dd>
                    </div>
                </dl>
            </template>
            <p v-else class="mt-3 text-sm text-ink-500" data-test="fleet-empty">Nenhum veículo cadastrado ainda.</p>
        </section>

        <div class="mt-4 grid gap-4 xl:grid-cols-5">
            <!-- Próximas viagens -->
            <section v-if="operations" class="card overflow-hidden xl:col-span-3" data-test="next-trips" aria-label="Próximas viagens">
                <div class="flex flex-wrap items-baseline justify-between gap-3 px-5 pt-5">
                    <h2 class="font-display text-base font-semibold text-ink-900">Próximas viagens</h2>
                    <RouterLink :to="{ name: 'operations' }" class="text-sm font-medium text-ink-600 underline decoration-ink-300 underline-offset-4 hover:text-ink-950">
                        Abrir operações
                    </RouterLink>
                </div>

                <ul v-if="operations.next_trips.length > 0" class="mt-3 divide-y divide-ink-100">
                    <li v-for="trip in operations.next_trips" :key="trip.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3" data-test="next-trip">
                        <p class="num w-40 shrink-0 whitespace-nowrap text-sm font-medium text-ink-900">{{ formatDateTime(trip.departure_time) }}</p>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink-900">{{ trip.line.origin }} → {{ trip.line.destination }}</p>
                            <p class="truncate text-xs text-ink-500">{{ trip.driver.name }}</p>
                        </div>
                        <MercosulPlate :plate="trip.vehicle.plate" size="sm" />
                    </li>
                </ul>
                <p v-else class="px-5 pb-5 pt-3 text-sm text-ink-500" data-test="next-trips-empty">Nenhuma viagem agendada daqui para a frente.</p>
            </section>

            <!-- Alertas -->
            <div class="space-y-4" :class="operations ? 'xl:col-span-2' : 'xl:col-span-5 xl:grid xl:grid-cols-2 xl:gap-4 xl:space-y-0'">
                <section v-if="crew" class="card p-5" data-test="license-alerts" aria-label="CNH a vencer">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="crew.expiring_licenses.length > 0 ? 'bg-signal-50 text-signal-600' : 'bg-road-50 text-road-600'">
                            <AppIcon name="license" />
                        </span>
                        <div>
                            <h2 class="font-display text-base font-semibold text-ink-900">CNH a vencer</h2>
                            <p class="text-xs text-ink-500">Motoristas ativos, próximos 30 dias</p>
                        </div>
                        <span v-if="crew.expiring_licenses.length > 0" class="num ml-auto text-2xl font-semibold text-ink-950" data-test="license-count">{{ crew.expiring_licenses.length }}</span>
                    </div>

                    <ul v-if="crew.expiring_licenses.length > 0" class="mt-4 space-y-2.5">
                        <li v-for="license in crew.expiring_licenses" :key="license.employee_id" class="flex items-baseline justify-between gap-3 text-sm" data-test="license-alert">
                            <span class="truncate font-medium text-ink-900">{{ license.name }}</span>
                            <span class="shrink-0 text-right">
                                <span class="block text-xs font-semibold" :class="license.days_remaining <= 0 ? 'text-brake-700' : 'text-signal-700'">{{ licenseLabel(license.days_remaining) }}</span>
                                <span class="num block text-xs text-ink-500">{{ formatDate(license.cnh_expiration_date) }}</span>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-ink-500" data-test="license-ok">Nenhuma CNH vence nos próximos 30 dias.</p>
                </section>

                <section v-if="fleet" class="card p-5" data-test="preventive-alerts" aria-label="Revisão preventiva vencida">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="fleet.preventive_maintenance_due.length > 0 ? 'bg-brake-50 text-brake-600' : 'bg-road-50 text-road-600'">
                            <AppIcon name="wrench" />
                        </span>
                        <div>
                            <h2 class="font-display text-base font-semibold text-ink-900">Revisão preventiva vencida</h2>
                            <p class="text-xs text-ink-500">Veículos que passaram da quilometragem programada</p>
                        </div>
                        <span v-if="fleet.preventive_maintenance_due.length > 0" class="num ml-auto text-2xl font-semibold text-ink-950" data-test="preventive-count">
                            {{ fleet.preventive_maintenance_due.length }}
                        </span>
                    </div>

                    <ul v-if="fleet.preventive_maintenance_due.length > 0" class="mt-4 space-y-2.5">
                        <li v-for="vehicle in fleet.preventive_maintenance_due" :key="vehicle.id" class="flex items-center justify-between gap-3 text-sm" data-test="preventive-alert">
                            <MercosulPlate :plate="vehicle.plate" size="sm" />
                            <span class="text-right">
                                <span class="num block text-xs font-semibold text-brake-700">{{ formatKm(vehicle.current_mileage - vehicle.next_preventive_maintenance_km) }} além</span>
                                <span class="num block text-xs text-ink-500">revisão aos {{ formatKm(vehicle.next_preventive_maintenance_km) }}</span>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-ink-500" data-test="preventive-ok">Nenhum veículo com revisão vencida.</p>
                </section>
            </div>
        </div>
    </template>
</template>
