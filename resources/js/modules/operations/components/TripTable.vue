<script setup lang="ts">
import MercosulPlate from '@/core/ui/MercosulPlate.vue';
import StatusBadge from '@/core/ui/StatusBadge.vue';
import { formatDateTime, formatKm } from '@/core/utils/format';
import type { TripSummary, TripTransition } from '../types/trip';

defineProps<{ trips: TripSummary[]; busyId: string | null; canManage: boolean }>();
const emit = defineEmits<{ transition: [trip: TripSummary, action: TripTransition] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[900px] [&_td]:!px-3.5 [&_th]:!px-3.5">
            <thead>
                <tr>
                    <th scope="col">Linha</th>
                    <th scope="col">Partida</th>
                    <th scope="col">Chegada</th>
                    <th scope="col">Veículo</th>
                    <th scope="col">Motorista</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="trip in trips" :key="trip.id" data-test="trip-row">
                    <td>
                        <span class="block whitespace-nowrap font-medium text-ink-900">{{ trip.line.origin }} → {{ trip.line.destination }}</span>
                        <span class="num block text-xs text-ink-400">{{ formatKm(trip.line.distance_km) }}</span>
                    </td>
                    <td class="num whitespace-nowrap" data-test="departure">{{ formatDateTime(trip.departure_time) }}</td>
                    <td class="num whitespace-nowrap text-ink-600">{{ formatDateTime(trip.arrival_time) }}</td>
                    <td><MercosulPlate :plate="trip.vehicle.plate" size="sm" /></td>
                    <td class="whitespace-nowrap">{{ trip.driver.name }}</td>
                    <td><StatusBadge kind="trip" :status="trip.status" /></td>
                    <td>
                        <div v-if="canManage" class="flex justify-end gap-2">
                            <template v-if="trip.status === 'scheduled'">
                                <button type="button" class="btn btn-sm btn-primary" data-test="start" :disabled="busyId === trip.id" @click="emit('transition', trip, 'start')">
                                    Iniciar
                                </button>
                                <button type="button" class="btn btn-sm btn-quiet" data-test="cancel" :disabled="busyId === trip.id" @click="emit('transition', trip, 'cancel')">
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
