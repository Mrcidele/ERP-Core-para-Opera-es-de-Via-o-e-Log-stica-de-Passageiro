<script setup lang="ts">
import AppIcon from '@/core/components/AppIcon.vue';
import { formatKm } from '@/core/utils/format';
import { VEHICLE_CLASS_LABELS, type Vehicle } from '../types/vehicle';
import PlateBadge from './PlateBadge.vue';
import StatusBadge from './StatusBadge.vue';

defineProps<{ vehicles: Vehicle[]; canManage: boolean }>();
const emit = defineEmits<{ edit: [vehicle: Vehicle]; remove: [vehicle: Vehicle] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left">
            <thead>
                <tr class="border-b border-ink-100 text-sm text-ink-500">
                    <th scope="col" class="px-5 py-3 font-medium">Placa</th>
                    <th scope="col" class="px-5 py-3 font-medium">Classe</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Lugares</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Quilometragem</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="vehicle in vehicles"
                    :key="vehicle.id"
                    data-test="vehicle-row"
                    class="border-b border-ink-100 last:border-0 hover:bg-ink-50/60"
                >
                    <td class="px-5 py-3">
                        <PlateBadge :plate="vehicle.plate" />
                        <span class="mt-1 block text-xs tabular-nums text-ink-400">{{ vehicle.chassis }}</span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-3">{{ VEHICLE_CLASS_LABELS[vehicle.class_type] }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ vehicle.capacity }}</td>
                    <td class="px-5 py-3 text-right tabular-nums">{{ formatKm(vehicle.current_mileage) }}</td>
                    <td class="px-5 py-3"><StatusBadge :status="vehicle.status" /></td>
                    <td class="px-5 py-3">
                        <div v-if="canManage" class="flex justify-end gap-1">
                            <button
                                type="button"
                                class="icon-btn"
                                data-test="edit"
                                :aria-label="`Editar veículo ${vehicle.plate}`"
                                @click="emit('edit', vehicle)"
                            >
                                <AppIcon name="pencil" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn hover:!bg-brake-100 hover:!text-brake-700"
                                data-test="remove"
                                :aria-label="`Excluir veículo ${vehicle.plate}`"
                                @click="emit('remove', vehicle)"
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
