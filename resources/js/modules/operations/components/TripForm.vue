<script setup lang="ts">
import { formatDuration, formatKg, formatKm } from '@/core/utils/format';
import { VEHICLE_TYPE_LABELS } from '@/modules/fleet/types/vehicle';
import { useTripForm } from '../composables/useTripForm';
import type { TripPrefill } from '../types/timeline';
import type { Trip } from '../types/trip';

const props = withDefaults(defineProps<{ initial?: TripPrefill }>(), { initial: () => ({}) });
const emit = defineEmits<{ scheduled: [trip: Trip]; cancel: [] }>();

const { form, errors, selectedLine, submit, store } = useTripForm((trip) => emit('scheduled', trip), props.initial);
</script>

<template>
    <div v-if="store.optionsStatus === 'loading' || store.optionsStatus === 'idle'" data-test="options-loading" class="space-y-3" aria-busy="true">
        <div v-for="row in 5" :key="row" class="h-11 animate-pulse rounded-md bg-ink-50" />
        <span class="sr-only">Carregando rotas, veículos e motoristas</span>
    </div>

    <div v-else-if="store.optionsStatus === 'error'" data-test="options-error">
        <h3 class="font-display text-lg font-semibold">Não foi possível carregar rotas, veículos e motoristas</h3>
        <p class="mt-1 text-ink-500">Verifique a conexão e tente de novo.</p>
        <button type="button" class="btn btn-quiet mt-5" @click="store.loadOptions()">Tentar novamente</button>
    </div>

    <form v-else class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <div>
            <label class="field-label" for="trip-line">Rota</label>
            <select id="trip-line" v-model="form.line_id" data-test="line_id" class="field-input" :aria-invalid="Boolean(errors.line_id)">
                <option value="" disabled>Selecione</option>
                <option v-for="line in store.lines" :key="line.id" :value="line.id">{{ line.origin }} → {{ line.destination }}</option>
            </select>
            <p v-if="errors.line_id" data-test="line_id-error" class="field-error">{{ errors.line_id }}</p>
            <p v-else-if="store.lines.length === 0" data-test="no-lines" class="field-hint">Nenhuma rota cadastrada.</p>
            <p v-else-if="selectedLine" data-test="line-hint" class="field-hint">
                {{ formatKm(selectedLine.distance_km) }}, duração estimada de {{ formatDuration(selectedLine.estimated_duration_minutes) }}.
            </p>
        </div>

        <div>
            <label class="field-label" for="trip-vehicle">Veículo</label>
            <select id="trip-vehicle" v-model="form.vehicle_id" data-test="vehicle_id" class="field-input" :aria-invalid="Boolean(errors.vehicle_id)">
                <option value="" disabled>Selecione</option>
                <option v-for="vehicle in store.vehicles" :key="vehicle.id" :value="vehicle.id">
                    {{ vehicle.plate }} ({{ VEHICLE_TYPE_LABELS[vehicle.vehicle_type] }}, {{ formatKg(vehicle.payload_kg) }})
                </option>
            </select>
            <p v-if="errors.vehicle_id" data-test="vehicle_id-error" class="field-error">{{ errors.vehicle_id }}</p>
            <p v-else-if="store.vehicles.length === 0" data-test="no-vehicles" class="field-hint">Nenhum veículo ativo na frota.</p>
            <p v-else class="field-hint">Só veículos ativos aparecem aqui.</p>
        </div>

        <div>
            <label class="field-label" for="trip-driver">Motorista</label>
            <select id="trip-driver" v-model="form.driver_id" data-test="driver_id" class="field-input" :aria-invalid="Boolean(errors.driver_id)">
                <option value="" disabled>Selecione</option>
                <option v-for="driver in store.drivers" :key="driver.id" :value="driver.id">
                    {{ driver.name }}<template v-if="driver.cnh_category"> (CNH {{ driver.cnh_category }})</template>
                </option>
            </select>
            <p v-if="errors.driver_id" data-test="driver_id-error" class="field-error">{{ errors.driver_id }}</p>
            <p v-else-if="store.drivers.length === 0" data-test="no-drivers" class="field-hint">Nenhum motorista ativo na equipe.</p>
            <p v-else class="field-hint">Só motoristas ativos aparecem aqui.</p>
        </div>

        <div class="space-y-5">
            <div>
                <label class="field-label" for="trip-departure">Partida</label>
                <input
                    id="trip-departure"
                    v-model="form.departure_time"
                    data-test="departure_time"
                    class="field-input num"
                    type="datetime-local"
                    :aria-invalid="Boolean(errors.departure_time)"
                />
                <p v-if="errors.departure_time" data-test="departure_time-error" class="field-error">{{ errors.departure_time }}</p>
            </div>

            <div>
                <label class="field-label" for="trip-arrival">Chegada</label>
                <input
                    id="trip-arrival"
                    v-model="form.arrival_time"
                    data-test="arrival_time"
                    class="field-input num"
                    type="datetime-local"
                    :min="form.departure_time || undefined"
                    :aria-invalid="Boolean(errors.arrival_time)"
                />
                <p v-if="errors.arrival_time" data-test="arrival_time-error" class="field-error">{{ errors.arrival_time }}</p>
            </div>
        </div>

        <div class="drawer-actions">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Agendando…' : 'Agendar viagem' }}
            </button>
        </div>
    </form>
</template>
