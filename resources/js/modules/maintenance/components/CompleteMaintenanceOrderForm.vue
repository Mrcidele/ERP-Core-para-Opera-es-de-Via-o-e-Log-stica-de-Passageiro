<script setup lang="ts">
import { computed, ref } from 'vue';
import { completeMaintenanceOrderSchema } from '../schemas/maintenanceOrderSchemas';
import { useMaintenanceStore } from '../stores/maintenanceStore';
import { MAINTENANCE_TYPE_LABELS, type MaintenanceOrder, type MaintenanceOrderSummary } from '../types/maintenance';

const props = defineProps<{ order: MaintenanceOrderSummary }>();
const emit = defineEmits<{ completed: [order: MaintenanceOrder]; cancel: [] }>();

const store = useMaintenanceStore();
const cost = ref('');
const clientError = ref<string | null>(null);

store.resetSaveState();

const costError = computed(() => clientError.value ?? store.validationErrors.cost_cents?.[0] ?? null);

async function submit(): Promise<void> {
    const result = completeMaintenanceOrderSchema.safeParse({ cost: cost.value });

    if (!result.success) {
        clientError.value = result.error.issues[0]?.message ?? null;

        return;
    }

    clientError.value = null;

    const order = await store.complete(props.order.id, result.data);

    if (order) {
        emit('completed', order);
    }
}
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <dl class="space-y-2 rounded-lg bg-ink-50 p-4 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-ink-500">Tipo</dt>
                <dd class="font-medium">{{ MAINTENANCE_TYPE_LABELS[order.type] }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-ink-500">Mecânico</dt>
                <dd class="font-medium">{{ order.mechanic.name }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">Serviço</dt>
                <dd class="mt-0.5">{{ order.description }}</dd>
            </div>
        </dl>

        <div>
            <label class="field-label" for="order-cost">Custo final (R$)</label>
            <input
                id="order-cost"
                v-model="cost"
                data-test="cost"
                class="field-input tabular-nums"
                inputmode="decimal"
                placeholder="0,00"
                autocomplete="off"
                :aria-invalid="Boolean(costError)"
            />
            <p v-if="costError" data-test="cost-error" class="field-error">{{ costError }}</p>
            <p v-else class="field-hint">Se não houver outra ordem pendente, o veículo {{ order.vehicle.plate }} volta a ficar ativo.</p>
        </div>

        <div class="flex justify-end gap-3 border-t border-ink-100 pt-5">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Concluindo…' : 'Concluir ordem' }}
            </button>
        </div>
    </form>
</template>
