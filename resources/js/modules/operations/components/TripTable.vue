<script setup lang="ts">
import { formatDateTime, formatKm } from '@/core/utils/format';
import PlateBadge from '@/modules/fleet/components/PlateBadge.vue';
import type { TripSummary, TripTransition } from '../types/trip';
import TripStatusBadge from './TripStatusBadge.vue';

defineProps<{ trips: TripSummary[]; busyId: string | null }>();
const emit = defineEmits<{ transition: [trip: TripSummary, action: TripTransition] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-left">
            <thead>
                <tr class="border-b border-ink-100 text-sm text-ink-500">
                    <th scope="col" class="px-5 py-3 font-medium">Linha</th>
                    <th scope="col" class="px-5 py-3 font-medium">Partida</th>
                    <th scope="col" class="px-5 py-3 font-medium">Chegada</th>
                    <th scope="col" class="px-5 py-3 font-medium">Veículo</th>
                    <th scope="col" class="px-5 py-3 font-medium">Motorista</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="trip in trips" :key="trip.id" data-test="trip-row" class="border-b border-ink-100 last:border-0 hover:bg-ink-50/60">
                    <td class="px-5 py-3">
                        <span class="block whitespace-nowrap font-medium">{{ trip.line.origin }} → {{ trip.line.destination }}</span>
                        <span class="block text-xs tabular-nums text-ink-400">{{ formatKm(trip.line.distance_km) }}</span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-3 tabular-nums" data-test="departure">{{ formatDateTime(trip.departure_time) }}</td>
                    <td class="whitespace-nowrap px-5 py-3 tabular-nums">{{ formatDateTime(trip.arrival_time) }}</td>
                    <td class="px-5 py-3"><PlateBadge :plate="trip.vehicle.plate" /></td>
                    <td class="whitespace-nowrap px-5 py-3">{{ trip.driver.name }}</td>
                    <td class="px-5 py-3"><TripStatusBadge :status="trip.status" /></td>
                    <td class="px-5 py-3">
                        <div class="flex justify-end gap-2">
                            <template v-if="trip.status === 'scheduled'">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    data-test="start"
                                    :disabled="busyId === trip.id"
                                    @click="emit('transition', trip, 'start')"
                                >
                                    Iniciar
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-quiet"
                                    data-test="cancel"
                                    :disabled="busyId === trip.id"
                                    @click="emit('transition', trip, 'cancel')"
                                >
                                    Cancelar
                                </button>
                            </template>
                            <button
                                v-else-if="trip.status === 'in_progress'"
                                type="button"
                                class="btn btn-sm btn-primary"
                                data-test="complete"
                                :disabled="busyId === trip.id"
                                @click="emit('transition', trip, 'complete')"
                            >
                                Concluir
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
