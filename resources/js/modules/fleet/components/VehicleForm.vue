<script setup lang="ts">
import { useVehicleForm } from '../composables/useVehicleForm';
import { VEHICLE_CLASS_LABELS, VEHICLE_CLASSES } from '../types/vehicle';

const { form, errors, submit, store } = useVehicleForm();

const inputClass = 'mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none';
</script>

<template>
    <form class="space-y-4 rounded-lg bg-white p-6 shadow" novalidate @submit.prevent="submit">
        <p v-if="store.status === 'success' && store.lastCreated" data-test="success" class="rounded bg-green-50 p-3 text-green-800">
            Veículo {{ store.lastCreated.plate }} cadastrado.
        </p>
        <p v-if="store.errorMessage" data-test="error" class="rounded bg-red-50 p-3 text-red-800">
            {{ store.errorMessage }}
        </p>

        <label class="block">
            <span class="text-sm font-medium">Placa</span>
            <input v-model="form.plate" data-test="plate" :class="inputClass" maxlength="8" placeholder="ABC1D23" />
            <span v-if="errors.plate" data-test="plate-error" class="text-sm text-red-600">{{ errors.plate }}</span>
        </label>

        <label class="block">
            <span class="text-sm font-medium">Chassi</span>
            <input v-model="form.chassis" data-test="chassis" :class="inputClass" maxlength="17" />
            <span v-if="errors.chassis" data-test="chassis-error" class="text-sm text-red-600">{{ errors.chassis }}</span>
        </label>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="text-sm font-medium">Capacidade</span>
                <input v-model.number="form.capacity" data-test="capacity" :class="inputClass" type="number" min="1" max="100" />
                <span v-if="errors.capacity" data-test="capacity-error" class="text-sm text-red-600">{{ errors.capacity }}</span>
            </label>

            <label class="block">
                <span class="text-sm font-medium">Classe</span>
                <select v-model="form.class_type" data-test="class_type" :class="inputClass">
                    <option value="" disabled>Selecione</option>
                    <option v-for="vehicleClass in VEHICLE_CLASSES" :key="vehicleClass" :value="vehicleClass">
                        {{ VEHICLE_CLASS_LABELS[vehicleClass] }}
                    </option>
                </select>
                <span v-if="errors.class_type" data-test="class_type-error" class="text-sm text-red-600">{{ errors.class_type }}</span>
            </label>

            <label class="block">
                <span class="text-sm font-medium">Quilometragem</span>
                <input v-model.number="form.current_mileage" data-test="current_mileage" :class="inputClass" type="number" min="0" />
                <span v-if="errors.current_mileage" data-test="current_mileage-error" class="text-sm text-red-600">
                    {{ errors.current_mileage }}
                </span>
            </label>
        </div>

        <button
            type="submit"
            data-test="submit"
            class="rounded bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            :disabled="store.status === 'loading'"
        >
            {{ store.status === 'loading' ? 'Salvando…' : 'Cadastrar' }}
        </button>
    </form>
</template>
