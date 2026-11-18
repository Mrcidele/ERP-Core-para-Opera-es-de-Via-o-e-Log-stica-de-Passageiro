<script setup lang="ts">
import { formatKm, formatMetersPerLiter } from '@/core/utils/format';
import { useVehicleForm } from '../composables/useVehicleForm';
import { VEHICLE_STATUS_LABELS, VEHICLE_STATUSES, VEHICLE_TYPE_LABELS, VEHICLE_TYPES, type Vehicle } from '../types/vehicle';

const props = withDefaults(defineProps<{ vehicle?: Vehicle | null }>(), { vehicle: null });
const emit = defineEmits<{ saved: [vehicle: Vehicle]; cancel: [] }>();

const { form, errors, submit, store, typeDefaultMetersPerLiter } = useVehicleForm(props.vehicle, (saved) => emit('saved', saved));
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
                class="field-input num font-semibold uppercase tracking-widest"
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
                class="field-input num uppercase"
                maxlength="17"
                autocomplete="off"
                :aria-invalid="Boolean(errors.chassis)"
            />
            <p v-if="errors.chassis" data-test="chassis-error" class="field-error">{{ errors.chassis }}</p>
            <p v-else class="field-hint">17 caracteres, sem as letras I, O e Q.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="field-label" for="vehicle-type">Tipo</label>
                <select
                    id="vehicle-type"
                    v-model="form.vehicle_type"
                    data-test="vehicle_type"
                    class="field-input"
                    :aria-invalid="Boolean(errors.vehicle_type)"
                >
                    <option value="" disabled>Selecione</option>
                    <option v-for="type in VEHICLE_TYPES" :key="type" :value="type">
                        {{ VEHICLE_TYPE_LABELS[type] }}
                    </option>
                </select>
                <p v-if="errors.vehicle_type" data-test="vehicle_type-error" class="field-error">{{ errors.vehicle_type }}</p>
            </div>

            <div>
                <label class="field-label" for="vehicle-payload">Carga útil (kg)</label>
                <input
                    id="vehicle-payload"
                    v-model.number="form.payload_kg"
                    data-test="payload_kg"
                    class="field-input num"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="60000"
                    :aria-invalid="Boolean(errors.payload_kg)"
                />
                <p v-if="errors.payload_kg" data-test="payload_kg-error" class="field-error">{{ errors.payload_kg }}</p>
            </div>
        </div>

        <div>
            <label class="field-label" for="vehicle-model">Modelo</label>
            <input
                id="vehicle-model"
                v-model="form.model"
                data-test="model"
                class="field-input"
                maxlength="60"
                placeholder="Volvo FH 540"
                autocomplete="off"
                :aria-invalid="Boolean(errors.model)"
            />
            <p v-if="errors.model" data-test="model-error" class="field-error">{{ errors.model }}</p>
            <p v-else class="field-hint">Opcional.</p>
        </div>

        <div>
            <label class="field-label" for="vehicle-mileage">Quilometragem (km)</label>
            <input
                id="vehicle-mileage"
                v-model.number="form.current_mileage"
                data-test="current_mileage"
                class="field-input num"
                type="number"
                inputmode="numeric"
                min="0"
                :aria-invalid="Boolean(errors.current_mileage)"
            />
            <p v-if="errors.current_mileage" data-test="current_mileage-error" class="field-error">{{ errors.current_mileage }}</p>
            <p v-else-if="vehicle" class="field-hint">Atual: {{ formatKm(vehicle.current_mileage) }}. O valor não pode diminuir.</p>
        </div>

        <div>
            <label class="field-label" for="vehicle-next-preventive">Próxima revisão preventiva (km)</label>
            <input
                id="vehicle-next-preventive"
                v-model.number="form.next_preventive_maintenance_km"
                data-test="next_preventive_maintenance_km"
                class="field-input num"
                type="number"
                inputmode="numeric"
                min="0"
                :aria-invalid="Boolean(errors.next_preventive_maintenance_km)"
            />
            <p v-if="errors.next_preventive_maintenance_km" data-test="next_preventive_maintenance_km-error" class="field-error">
                {{ errors.next_preventive_maintenance_km }}
            </p>
            <p v-else class="field-hint">Opcional. Ao alcançar essa quilometragem, o veículo aparece como revisão vencida.</p>
        </div>

        <div>
            <label class="field-label" for="vehicle-min-km-per-liter">Meta de rendimento (km/L)</label>
            <input
                id="vehicle-min-km-per-liter"
                v-model.number="form.min_km_per_liter"
                data-test="min_km_per_liter"
                class="field-input num"
                type="number"
                inputmode="decimal"
                step="0.1"
                min="0.5"
                max="30"
                :placeholder="typeDefaultMetersPerLiter === null ? '' : String(typeDefaultMetersPerLiter / 1000).replace('.', ',')"
                :aria-invalid="Boolean(errors.min_km_per_liter)"
            />
            <p v-if="errors.min_km_per_liter" data-test="min_km_per_liter-error" class="field-error">{{ errors.min_km_per_liter }}</p>
            <p v-else data-test="min_km_per_liter-hint" class="field-hint">
                Abastecimento abaixo da meta gera alerta de consumo alto.
                <template v-if="typeDefaultMetersPerLiter !== null">
                    Em branco, vale o padrão do tipo: <span class="num">{{ formatMetersPerLiter(typeDefaultMetersPerLiter) }}</span>.
                </template>
            </p>
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

        <div class="drawer-actions">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Salvando…' : vehicle ? 'Salvar alterações' : 'Cadastrar veículo' }}
            </button>
        </div>
    </form>
</template>
