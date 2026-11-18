<script setup lang="ts">
import AppIcon from '@/core/ui/AppIcon.vue';
import MercosulPlate from '@/core/ui/MercosulPlate.vue';
import StatusBadge from '@/core/ui/StatusBadge.vue';
import { formatKg, formatKm, formatMetersPerLiter } from '@/core/utils/format';
import { VEHICLE_TYPE_LABELS, type Vehicle } from '../types/vehicle';
import PreventiveProgressBar from './PreventiveProgressBar.vue';

defineProps<{ vehicles: Vehicle[]; canManage: boolean }>();
const emit = defineEmits<{ edit: [vehicle: Vehicle]; remove: [vehicle: Vehicle] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[960px]">
            <thead>
                <tr>
                    <th scope="col">Placa</th>
                    <th scope="col">Chassi</th>
                    <th scope="col">Tipo</th>
                    <th scope="col" class="text-right">Carga útil</th>
                    <th scope="col" class="text-right">Meta</th>
                    <th scope="col" class="text-right">Quilometragem</th>
                    <th scope="col">Próxima preventiva</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="vehicle in vehicles" :key="vehicle.id" data-test="vehicle-row">
                    <td><MercosulPlate :plate="vehicle.plate" /></td>
                    <td class="num text-xs text-ink-500">{{ vehicle.chassis }}</td>
                    <td class="whitespace-nowrap">
                        <span data-test="vehicle-type">{{ VEHICLE_TYPE_LABELS[vehicle.vehicle_type] }}</span>
                        <span v-if="vehicle.model" data-test="vehicle-model" class="block text-xs text-ink-500">{{ vehicle.model }}</span>
                    </td>
                    <td class="num whitespace-nowrap text-right">{{ formatKg(vehicle.payload_kg) }}</td>
                    <td class="num whitespace-nowrap text-right" data-test="fuel-target">
                        {{ formatMetersPerLiter(vehicle.effective_min_meters_per_liter) }}
                        <span v-if="vehicle.min_meters_per_liter === null" class="block text-xs font-sans text-ink-400">padrão do tipo</span>
                    </td>
                    <td class="num whitespace-nowrap text-right">{{ formatKm(vehicle.current_mileage) }}</td>
                    <td><PreventiveProgressBar :vehicle="vehicle" /></td>
                    <td><StatusBadge kind="vehicle" :status="vehicle.status" /></td>
                    <td>
                        <div v-if="canManage" class="flex justify-end gap-1">
                            <button type="button" class="icon-btn" data-test="edit" :aria-label="`Editar veículo ${vehicle.plate}`" @click="emit('edit', vehicle)">
                                <AppIcon name="pencil" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn hover:!bg-brake-50 hover:!text-brake-700"
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
