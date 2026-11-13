<script setup lang="ts">
import { formatKm } from '@/core/utils/format';
import { useVehicleForm } from '../composables/useVehicleForm';
import { VEHICLE_CLASS_LABELS, VEHICLE_CLASSES, VEHICLE_STATUS_LABELS, VEHICLE_STATUSES, type Vehicle } from '../types/vehicle';

const props = withDefaults(defineProps<{ vehicle?: Vehicle | null }>(), { vehicle: null });
const emit = defineEmits<{ saved: [vehicle: Vehicle]; cancel: [] }>();

const { form, errors, submit, store } = useVehicleForm(props.vehicle, (saved) => emit('saved', saved));
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <div>
            <label class="field-label" for="vehicle-plate">Placa</label>
            <input
                id="vehicle-plate"
                v-model="form.plate"
                data-test="plate"
                class="field-input font-display font-semibold uppercase tracking-widest"
                maxlength="8"
                placeholder="ABC1D23"
                autocomplete="off"
                :aria-invalid="Boolean(errors.plate)"
            />
            <p v-if="errors.plate" data-test="plate-error" class="field-error">{{ errors.plate }}</p>
        </div>

        <div>
            <label class="field-label" for="vehicle-chassis">Chassi</label>
            <input
                id="vehicle-chassis"
                v-model="form.chassis"
                data-test="chassis"
                class="field-input uppercase tabular-nums"
                maxlength="17"
                autocomplete="off"
                :aria-invalid="Boolean(errors.chassis)"
            />
            <p v-if="errors.chassis" data-test="chassis-error" class="field-error">{{ errors.chassis }}</p>
            <p v-else class="field-hint">17 caracteres, sem as letras I, O e Q.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="field-label" for="vehicle-class">Classe</label>
                <select
                    id="vehicle-class"
                    v-model="form.class_type"
                    data-test="class_type"
                    class="field-input"
                    :aria-invalid="Boolean(errors.class_type)"
                >
                    <option value="" disabled>Selecione</option>
                    <option v-for="vehicleClass in VEHICLE_CLASSES" :key="vehicleClass" :value="vehicleClass">
                        {{ VEHICLE_CLASS_LABELS[vehicleClass] }}
                    </option>
                </select>
                <p v-if="errors.class_type" data-test="class_type-error" class="field-error">{{ errors.class_type }}</p>
            </div>

            <div>
                <label class="field-label" for="vehicle-capacity">Capacidade (lugares)</label>
                <input
                    id="vehicle-capacity"
                    v-model.number="form.capacity"
                    data-test="capacity"
                    class="field-input tabular-nums"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="100"
                    :aria-invalid="Boolean(errors.capacity)"
                />
                <p v-if="errors.capacity" data-test="capacity-error" class="field-error">{{ errors.capacity }}</p>
            </div>
        </div>

        <div>
            <label class="field-label" for="vehicle-mileage">Quilometragem (km)</label>
            <input
                id="vehicle-mileage"
                v-model.number="form.current_mileage"
                data-test="current_mileage"
                class="field-input tabular-nums"
                type="number"
                inputmode="numeric"
                min="0"
                :aria-invalid="Boolean(errors.current_mileage)"
            />
            <p v-if="errors.current_mileage" data-test="current_mileage-error" class="field-error">{{ errors.current_mileage }}</p>
            <p v-else-if="vehicle" class="field-hint">Atual: {{ formatKm(vehicle.current_mileage) }}. O valor não pode diminuir.</p>
        </div>

        <fieldset v-if="vehicle">
            <legend class="field-label">Status</legend>
            <div class="mt-1.5 grid grid-cols-3 gap-2">
                <label
                    v-for="status in VEHICLE_STATUSES"
                    :key="status"
                    class="flex h-11 cursor-pointer items-center justify-center rounded-md border px-2 text-center text-sm font-medium transition-colors"
                    :class="
                        form.status === status
                            ? 'border-ink-900 bg-ink-900 text-white'
                            : 'border-ink-200 bg-white text-ink-700 hover:border-ink-300 hover:bg-ink-50'
                    "
                >
                    <input v-model="form.status" type="radio" name="vehicle-status" class="sr-only" :value="status" :data-test="`status-${status}`" />
                    {{ VEHICLE_STATUS_LABELS[status] }}
                </label>
            </div>
            <p v-if="errors.status" data-test="status-error" class="field-error">{{ errors.status }}</p>
        </fieldset>

        <div class="flex justify-end gap-3 border-t border-ink-100 pt-5">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Salvando…' : vehicle ? 'Salvar alterações' : 'Cadastrar veículo' }}
            </button>
        </div>
    </form>
</template>
