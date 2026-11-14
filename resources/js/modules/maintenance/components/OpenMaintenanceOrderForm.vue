<script setup lang="ts">
import { VEHICLE_STATUS_LABELS } from '@/modules/fleet/types/vehicle';
import { useOpenMaintenanceOrderForm } from '../composables/useOpenMaintenanceOrderForm';
import { MAINTENANCE_TYPE_LABELS, MAINTENANCE_TYPES, type MaintenanceOrder } from '../types/maintenance';

const emit = defineEmits<{ opened: [order: MaintenanceOrder]; cancel: [] }>();

const { form, errors, submit, store } = useOpenMaintenanceOrderForm((order) => emit('opened', order));
</script>

<template>
    <div v-if="store.optionsStatus === 'loading' || store.optionsStatus === 'idle'" data-test="options-loading" class="space-y-3" aria-busy="true">
        <div v-for="row in 4" :key="row" class="h-11 animate-pulse rounded-md bg-ink-50" />
        <span class="sr-only">Carregando veículos e mecânicos</span>
    </div>

    <div v-else-if="store.optionsStatus === 'error'" data-test="options-error">
        <h3 class="font-display text-lg font-semibold">Não foi possível carregar veículos e mecânicos</h3>
        <p class="mt-1 text-ink-500">Verifique a conexão e tente de novo.</p>
        <button type="button" class="btn btn-quiet mt-5" @click="store.loadOptions()">Tentar novamente</button>
    </div>

    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <div>
            <label class="field-label" for="order-vehicle">Veículo</label>
            <select id="order-vehicle" v-model="form.vehicle_id" data-test="vehicle_id" class="field-input" :aria-invalid="Boolean(errors.vehicle_id)">
                <option value="" disabled>Selecione</option>
                <option v-for="vehicle in store.vehicles" :key="vehicle.id" :value="vehicle.id">
                    {{ vehicle.plate }} ({{ VEHICLE_STATUS_LABELS[vehicle.status] }})
                </option>
            </select>
            <p v-if="errors.vehicle_id" data-test="vehicle_id-error" class="field-error">{{ errors.vehicle_id }}</p>
            <p v-else-if="store.vehicles.length === 0" data-test="no-vehicles" class="field-hint">Nenhum veículo cadastrado.</p>
            <p v-else class="field-hint">O veículo fica em manutenção e sai da escala de viagens até a ordem ser encerrada.</p>
        </div>

        <fieldset>
            <legend class="field-label">Tipo</legend>
            <div class="mt-1.5 grid grid-cols-2 gap-2">
                <label
                    v-for="type in MAINTENANCE_TYPES"
                    :key="type"
                    class="flex h-11 cursor-pointer items-center justify-center rounded-md border px-2 text-center text-sm font-medium transition-colors"
                    :class="
                        form.type === type
                            ? 'border-ink-900 bg-ink-900 text-white'
                            : 'border-ink-200 bg-white text-ink-700 hover:border-ink-300 hover:bg-ink-50'
                    "
                >
                    <input v-model="form.type" type="radio" name="order-type" class="sr-only" :value="type" :data-test="`type-${type}`" />
                    {{ MAINTENANCE_TYPE_LABELS[type] }}
                </label>
            </div>
            <p v-if="errors.type" data-test="type-error" class="field-error">{{ errors.type }}</p>
        </fieldset>

        <div>
            <label class="field-label" for="order-mechanic">Mecânico responsável</label>
            <select id="order-mechanic" v-model="form.mechanic_id" data-test="mechanic_id" class="field-input" :aria-invalid="Boolean(errors.mechanic_id)">
                <option value="" disabled>Selecione</option>
                <option v-for="mechanic in store.mechanics" :key="mechanic.id" :value="mechanic.id">{{ mechanic.name }}</option>
            </select>
            <p v-if="errors.mechanic_id" data-test="mechanic_id-error" class="field-error">{{ errors.mechanic_id }}</p>
            <p v-else-if="store.mechanics.length === 0" data-test="no-mechanics" class="field-hint">Nenhum mecânico ativo na tripulação.</p>
        </div>

        <div>
            <label class="field-label" for="order-description">Serviço a fazer</label>
            <textarea
                id="order-description"
                v-model="form.description"
                data-test="description"
                class="field-input !h-28 py-2.5"
                maxlength="2000"
                :aria-invalid="Boolean(errors.description)"
            />
            <p v-if="errors.description" data-test="description-error" class="field-error">{{ errors.description }}</p>
        </div>

        <div class="flex justify-end gap-3 border-t border-ink-100 pt-5">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Abrindo…' : 'Abrir ordem de serviço' }}
            </button>
        </div>
    </form>
</template>
