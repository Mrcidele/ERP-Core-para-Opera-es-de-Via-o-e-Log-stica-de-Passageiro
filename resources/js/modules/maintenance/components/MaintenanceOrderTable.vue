<script setup lang="ts">
import MercosulPlate from '@/core/ui/MercosulPlate.vue';
import StatusBadge from '@/core/ui/StatusBadge.vue';
import { formatCents, formatDateTime } from '@/core/utils/format';
import { isPending, type MaintenanceOrderSummary } from '../types/maintenance';
import MaintenanceTypeBadge from './MaintenanceTypeBadge.vue';

defineProps<{ orders: MaintenanceOrderSummary[]; canManage: boolean }>();
const emit = defineEmits<{ complete: [order: MaintenanceOrderSummary]; cancel: [order: MaintenanceOrderSummary] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[980px]">
            <thead>
                <tr>
                    <th scope="col">Veículo</th>
                    <th scope="col">Serviço</th>
                    <th scope="col">Mecânico</th>
                    <th scope="col">Aberta em</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-right">Custo</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="order in orders" :key="order.id" data-test="order-row">
                    <td><MercosulPlate :plate="order.vehicle.plate" size="sm" /></td>
                    <td class="min-w-[16rem]">
                        <MaintenanceTypeBadge :type="order.type" />
                        <span class="mt-1 block text-ink-600">{{ order.description }}</span>
                    </td>
                    <td class="whitespace-nowrap">{{ order.mechanic.name }}</td>
                    <td class="num whitespace-nowrap">
                        {{ formatDateTime(order.opened_at) }}
                        <span v-if="order.completed_at" class="block text-xs text-ink-400">até {{ formatDateTime(order.completed_at) }}</span>
                    </td>
                    <td><StatusBadge kind="maintenance" :status="order.status" /></td>
                    <td class="num whitespace-nowrap text-right font-medium" data-test="cost">
                        {{ order.status === 'completed' ? formatCents(order.cost_cents) : '' }}
                    </td>
                    <td>
                        <div v-if="canManage && isPending(order.status)" class="flex justify-end gap-2">
                            <button type="button" class="btn btn-sm btn-primary" data-test="complete" @click="emit('complete', order)">Concluir</button>
                            <button type="button" class="btn btn-sm btn-quiet" data-test="cancel" @click="emit('cancel', order)">Cancelar</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
