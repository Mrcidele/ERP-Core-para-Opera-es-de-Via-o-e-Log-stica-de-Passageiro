<script setup lang="ts">
import { formatCents, formatDateTime } from '@/core/utils/format';
import PlateBadge from '@/modules/fleet/components/PlateBadge.vue';
import { isPending, type MaintenanceOrderSummary } from '../types/maintenance';
import MaintenanceStatusBadge from './MaintenanceStatusBadge.vue';
import MaintenanceTypeBadge from './MaintenanceTypeBadge.vue';

defineProps<{ orders: MaintenanceOrderSummary[] }>();
const emit = defineEmits<{ complete: [order: MaintenanceOrderSummary]; cancel: [order: MaintenanceOrderSummary] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-left">
            <thead>
                <tr class="border-b border-ink-100 text-sm text-ink-500">
                    <th scope="col" class="px-5 py-3 font-medium">Veículo</th>
                    <th scope="col" class="px-5 py-3 font-medium">Serviço</th>
                    <th scope="col" class="px-5 py-3 font-medium">Mecânico</th>
                    <th scope="col" class="px-5 py-3 font-medium">Aberta em</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Custo</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="order in orders" :key="order.id" data-test="order-row" class="border-b border-ink-100 align-top last:border-0 hover:bg-ink-50/60">
                    <td class="px-5 py-3"><PlateBadge :plate="order.vehicle.plate" /></td>
                    <td class="min-w-[16rem] px-5 py-3">
                        <MaintenanceTypeBadge :type="order.type" />
                        <span class="mt-1 block text-sm text-ink-600">{{ order.description }}</span>
                    </td>
                    <td class="whitespace-nowrap px-5 py-3">{{ order.mechanic.name }}</td>
                    <td class="whitespace-nowrap px-5 py-3 tabular-nums">
                        {{ formatDateTime(order.opened_at) }}
                        <span v-if="order.completed_at" class="block text-xs text-ink-400">Concluída em {{ formatDateTime(order.completed_at) }}</span>
                    </td>
                    <td class="px-5 py-3"><MaintenanceStatusBadge :status="order.status" /></td>
                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums" data-test="cost">
                        {{ order.status === 'completed' ? formatCents(order.cost_cents) : '' }}
                    </td>
                    <td class="px-5 py-3">
                        <div v-if="isPending(order.status)" class="flex justify-end gap-2">
                            <button type="button" class="btn btn-sm btn-primary" data-test="complete" @click="emit('complete', order)">Concluir</button>
                            <button type="button" class="btn btn-sm btn-quiet" data-test="cancel" @click="emit('cancel', order)">Cancelar</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
