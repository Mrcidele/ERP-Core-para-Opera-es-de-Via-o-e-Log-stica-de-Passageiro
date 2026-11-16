<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { toDateTimeInput } from '@/core/utils/datetime';
import { formatCents, formatKm } from '@/core/utils/format';
import { LITERS, litersToMilliliters, REAIS, reaisToCents } from '@/core/utils/money';
import { fuelLogSchema, type FuelLogFormField } from '../schemas/fuelLogSchema';
import { useFuelStore } from '../stores/fuelStore';
import type { FuelLog } from '../types/fuelLog';

const emit = defineEmits<{ recorded: [log: FuelLog]; cancel: [] }>();

const store = useFuelStore();

const form = reactive({
    vehicle_id: '',
    odometer_km: null as number | null | '',
    liters: '',
    total_cost: '',
    station_name: '',
    // O mais comum é registrar logo depois de abastecer.
    fueled_at: toDateTimeInput(new Date()),
});

type FieldErrors = Partial<Record<FuelLogFormField, string>>;

const clientErrors = ref<FieldErrors>({});

store.resetSaveState();
void store.loadOptions();

// A API nomeia os campos como os recebe; o formulário mostra o erro no campo em que o valor foi digitado.
const API_FIELDS: Record<string, FuelLogFormField> = { liters_milliliters: 'liters', total_cost_cents: 'total_cost' };

const errors = computed<FieldErrors>(() => {
    const merged = { ...clientErrors.value };

    for (const [field, messages] of Object.entries(store.validationErrors)) {
        const target = API_FIELDS[field] ?? (field as FuelLogFormField);
        const message = messages[0];

        if (message !== undefined && !(target in merged)) {
            merged[target] = message;
        }
    }

    return merged;
});

const selectedVehicle = computed(() => store.vehicles.find((vehicle) => vehicle.id === form.vehicle_id) ?? null);

/** Preço por litro, para o operador conferir o que digitou com o que a bomba mostrou. */
const pricePerLiter = computed(() => {
    if (!LITERS.test(form.liters.trim()) || !REAIS.test(form.total_cost.trim())) {
        return null;
    }

    const milliliters = litersToMilliliters(form.liters);

    return milliliters > 0 ? Math.round(reaisToCents(form.total_cost) / (milliliters / 1000)) : null;
});

async function submit(): Promise<void> {
    const result = fuelLogSchema.safeParse({ ...form, odometer_km: form.odometer_km === '' || form.odometer_km === null ? undefined : form.odometer_km });

    if (!result.success) {
        const fieldErrors: FieldErrors = {};

        for (const issue of result.error.issues) {
            fieldErrors[issue.path[0] as FuelLogFormField] ??= issue.message;
        }

        clientErrors.value = fieldErrors;

        return;
    }

    clientErrors.value = {};

    const log = await store.record(result.data);

    if (log) {
        emit('recorded', log);
    }
}
</script>

<template>
    <div v-if="store.optionsStatus === 'loading' || store.optionsStatus === 'idle'" data-test="options-loading" class="space-y-3" aria-busy="true">
        <div v-for="row in 5" :key="row" class="h-11 animate-pulse rounded-md bg-ink-50" />
        <span class="sr-only">Carregando veículos</span>
    </div>

    <div v-else-if="store.optionsStatus === 'error'" data-test="options-error">
        <h3 class="font-display text-lg font-semibold">Não foi possível carregar os veículos</h3>
        <p class="mt-1 text-ink-500">Verifique a conexão e tente de novo.</p>
        <button type="button" class="btn btn-quiet mt-5" @click="store.loadOptions()">Tentar novamente</button>
    </div>

    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <div>
            <label class="field-label" for="fuel-vehicle">Veículo</label>
            <select id="fuel-vehicle" v-model="form.vehicle_id" data-test="vehicle_id" class="field-input num" :aria-invalid="Boolean(errors.vehicle_id)">
                <option value="" disabled>Selecione</option>
                <option v-for="vehicle in store.vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.plate }}</option>
            </select>
            <p v-if="errors.vehicle_id" data-test="vehicle_id-error" class="field-error">{{ errors.vehicle_id }}</p>
            <p v-else-if="store.vehicles.length === 0" data-test="no-vehicles" class="field-hint">Nenhum veículo cadastrado na frota.</p>
        </div>

        <div>
            <label class="field-label" for="fuel-odometer">Hodômetro (km)</label>
            <input
                id="fuel-odometer"
                v-model.number="form.odometer_km"
                data-test="odometer_km"
                class="field-input num"
                type="number"
                inputmode="numeric"
                min="0"
                :aria-invalid="Boolean(errors.odometer_km)"
            />
            <p v-if="errors.odometer_km" data-test="odometer_km-error" class="field-error">{{ errors.odometer_km }}</p>
            <p v-else-if="selectedVehicle" data-test="odometer-hint" class="field-hint">
                No cadastro: <span class="num">{{ formatKm(selectedVehicle.current_mileage) }}</span>. Se o hodômetro estiver à frente, a quilometragem do veículo é atualizada.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="field-label" for="fuel-liters">Volume (litros)</label>
                <input
                    id="fuel-liters"
                    v-model="form.liters"
                    data-test="liters"
                    class="field-input num"
                    inputmode="decimal"
                    placeholder="350,5"
                    autocomplete="off"
                    :aria-invalid="Boolean(errors.liters)"
                />
                <p v-if="errors.liters" data-test="liters-error" class="field-error">{{ errors.liters }}</p>
            </div>
            <div>
                <label class="field-label" for="fuel-cost">Valor total (R$)</label>
                <input
                    id="fuel-cost"
                    v-model="form.total_cost"
                    data-test="total_cost"
                    class="field-input num"
                    inputmode="decimal"
                    placeholder="2.099,50"
                    autocomplete="off"
                    :aria-invalid="Boolean(errors.total_cost)"
                />
                <p v-if="errors.total_cost" data-test="total_cost-error" class="field-error">{{ errors.total_cost }}</p>
            </div>
        </div>
        <p v-if="pricePerLiter !== null" data-test="price-per-liter" class="field-hint !-mt-3">
            Preço por litro: <span class="num">{{ formatCents(pricePerLiter) }}</span>
        </p>

        <div>
            <label class="field-label" for="fuel-station">Posto</label>
            <input
                id="fuel-station"
                v-model="form.station_name"
                data-test="station_name"
                class="field-input"
                maxlength="120"
                placeholder="Nome do posto ou garagem"
                :aria-invalid="Boolean(errors.station_name)"
            />
            <p v-if="errors.station_name" data-test="station_name-error" class="field-error">{{ errors.station_name }}</p>
        </div>

        <div>
            <label class="field-label" for="fuel-date">Data e hora do abastecimento</label>
            <input id="fuel-date" v-model="form.fueled_at" data-test="fueled_at" class="field-input num" type="datetime-local" :aria-invalid="Boolean(errors.fueled_at)" />
            <p v-if="errors.fueled_at" data-test="fueled_at-error" class="field-error">{{ errors.fueled_at }}</p>
            <p v-else class="field-hint">No horário deste computador. Os abastecimentos de um veículo entram em ordem de data.</p>
        </div>

        <div class="drawer-actions">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Registrando…' : 'Registrar abastecimento' }}
            </button>
        </div>
    </form>
</template>
