<script setup lang="ts">
import AppIcon from '@/core/ui/AppIcon.vue';
import { formatDuration, formatKm } from '@/core/utils/format';
import type { Line } from '../types/trip';
import RouteStrip from './RouteStrip.vue';

defineProps<{ lines: Line[]; canManage: boolean }>();
const emit = defineEmits<{ edit: [line: Line]; remove: [line: Line] }>();

const stopNames = (line: Line): string => line.stops.map((stop) => stop.city_name).join(', ');
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[820px]">
            <thead>
                <tr>
                    <th scope="col">Linha</th>
                    <th scope="col" class="w-[38%]">Itinerário</th>
                    <th scope="col" class="text-right">Distância</th>
                    <th scope="col" class="text-right">Duração</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in lines" :key="line.id" data-test="line-row">
                    <td class="whitespace-nowrap font-medium text-ink-900" data-test="line-name">{{ line.origin }} → {{ line.destination }}</td>
                    <td>
                        <RouteStrip :distance-km="line.distance_km" :stops="line.stops" />
                        <p class="mt-1.5 text-xs text-ink-500" data-test="line-stops">
                            <template v-if="line.stops.length === 0">Direta, sem paradas</template>
                            <template v-else>
                                <span class="num">{{ line.stops.length }}</span>
                                {{ line.stops.length === 1 ? 'parada' : 'paradas' }}: {{ stopNames(line) }}
                            </template>
                        </p>
                    </td>
                    <td class="num whitespace-nowrap text-right">{{ formatKm(line.distance_km) }}</td>
                    <td class="num whitespace-nowrap text-right">{{ formatDuration(line.estimated_duration_minutes) }}</td>
                    <td>
                        <div v-if="canManage" class="flex justify-end gap-1">
                            <button
                                type="button"
                                class="icon-btn"
                                data-test="edit"
                                :aria-label="`Editar linha ${line.origin} a ${line.destination}`"
                                @click="emit('edit', line)"
                            >
                                <AppIcon name="pencil" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn hover:!bg-brake-50 hover:!text-brake-700"
                                data-test="remove"
                                :aria-label="`Excluir linha ${line.origin} a ${line.destination}`"
                                @click="emit('remove', line)"
                            >
                                <AppIcon name="trash" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
