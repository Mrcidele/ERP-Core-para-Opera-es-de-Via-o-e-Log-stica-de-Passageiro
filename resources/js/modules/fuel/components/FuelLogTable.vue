<script setup lang="ts">
import AppIcon from '@/core/ui/AppIcon.vue';
import MercosulPlate from '@/core/ui/MercosulPlate.vue';
import { formatCents, formatDateTime, formatKm, formatKmPerLiter, formatLiters } from '@/core/utils/format';
import type { FuelLog } from '../types/fuelLog';

defineProps<{ logs: FuelLog[]; canManage: boolean }>();
const emit = defineEmits<{ remove: [log: FuelLog] }>();

const pricePerLiter = (log: FuelLog): string => formatCents(Math.round(log.total_cost_cents / (log.liters_milliliters / 1000)));
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[960px]">
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    <th scope="col">Veículo</th>
                    <th scope="col">Posto</th>
                    <th scope="col" class="text-right">Hodômetro</th>
                    <th scope="col" class="text-right">Volume</th>
                    <th scope="col" class="text-right">Valor</th>
                    <th scope="col" class="text-right">Rendimento</th>
                    <th v-if="canManage" scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="log in logs" :key="log.id" data-test="fuel-row">
                    <td class="num whitespace-nowrap" data-test="fueled-at">{{ formatDateTime(log.fueled_at) }}</td>
                    <td><MercosulPlate :plate="log.vehicle.plate" size="sm" /></td>
                    <td>
                        <span class="block text-ink-900">{{ log.station_name }}</span>
                        <span class="block text-xs text-ink-500">por {{ log.recorded_by.name }}</span>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <span class="num block">{{ formatKm(log.odometer_km) }}</span>
                        <span v-if="log.distance_km !== null" class="num block text-xs text-ink-500" data-test="distance">+{{ formatKm(log.distance_km) }}</span>
                    </td>
                    <td class="num whitespace-nowrap text-right" data-test="row-liters">{{ formatLiters(log.liters_milliliters) }}</td>
                    <td class="whitespace-nowrap text-right">
                        <span class="num block" data-test="row-cost">{{ formatCents(log.total_cost_cents) }}</span>
                        <span class="num block text-xs text-ink-500">{{ pricePerLiter(log) }}/L</span>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <template v-if="log.km_per_liter !== null">
                            <span class="num block font-semibold" :class="log.low_efficiency_alert ? 'text-brake-700' : 'text-ink-900'" data-test="efficiency">
                                {{ formatKmPerLiter(log.km_per_liter) }}
                            </span>
                            <span
                                v-if="log.low_efficiency_alert"
                                data-test="low-efficiency"
                                class="mt-1 inline-flex items-center rounded-md bg-brake-50 px-1.5 py-0.5 text-xs font-semibold text-brake-700 ring-1 ring-inset ring-brake-600/20"
                            >
                                Consumo alto
                            </span>
                        </template>
                        <span v-else class="text-xs text-ink-400" data-test="no-efficiency">
                            {{ log.distance_km === null ? 'Primeiro registro' : 'Sem rodagem' }}
                        </span>
                    </td>
                    <td v-if="canManage" class="w-12">
                        <button
                            type="button"
                            class="icon-btn hover:!bg-brake-50 hover:!text-brake-700"
                            data-test="remove"
                            :aria-label="`Excluir abastecimento de ${log.vehicle.plate} em ${formatDateTime(log.fueled_at)}`"
                            @click="emit('remove', log)"
                        >
                            <AppIcon name="trash" />
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
