<script setup lang="ts">
import { useEmployeeForm } from '../composables/useEmployeeForm';
import { todayIso } from '../schemas/createEmployeeSchema';
import {
    CNH_CATEGORIES,
    EMPLOYEE_ROLE_LABELS,
    EMPLOYEE_ROLES,
    EMPLOYEE_STATUS_LABELS,
    EMPLOYEE_STATUSES,
    type Employee,
} from '../types/employee';

const emit = defineEmits<{ saved: [employee: Employee]; cancel: [] }>();

const { form, errors, submit, store } = useEmployeeForm((saved) => emit('saved', saved));

const choiceClass = (selected: boolean): string =>
    selected ? 'border-ink-900 bg-ink-900 text-white' : 'border-ink-200 bg-white text-ink-700 hover:border-ink-300 hover:bg-ink-50';
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <div>
            <label class="field-label" for="employee-name">Nome completo</label>
            <input
                id="employee-name"
                v-model="form.name"
                data-test="name"
                class="field-input"
                maxlength="120"
                autocomplete="off"
                :aria-invalid="Boolean(errors.name)"
            />
            <p v-if="errors.name" data-test="name-error" class="field-error">{{ errors.name }}</p>
        </div>

        <div>
            <label class="field-label" for="employee-cpf">CPF</label>
            <input
                id="employee-cpf"
                v-model="form.cpf"
                data-test="cpf"
                class="field-input tabular-nums"
                inputmode="numeric"
                maxlength="14"
                placeholder="000.000.000-00"
                autocomplete="off"
                :aria-invalid="Boolean(errors.cpf)"
            />
            <p v-if="errors.cpf" data-test="cpf-error" class="field-error">{{ errors.cpf }}</p>
        </div>

        <fieldset>
            <legend class="field-label">Cargo</legend>
            <div class="mt-1.5 grid grid-cols-3 gap-2">
                <label
                    v-for="role in EMPLOYEE_ROLES"
                    :key="role"
                    class="flex h-11 cursor-pointer items-center justify-center rounded-md border px-2 text-center text-sm font-medium transition-colors"
                    :class="choiceClass(form.role === role)"
                >
                    <input v-model="form.role" type="radio" name="employee-role" class="sr-only" :value="role" :data-test="`role-${role}`" />
                    {{ EMPLOYEE_ROLE_LABELS[role] }}
                </label>
            </div>
            <p v-if="errors.role" data-test="role-error" class="field-error">{{ errors.role }}</p>
        </fieldset>

        <fieldset v-if="form.role === 'driver'" data-test="cnh-fields" class="space-y-5 rounded-lg bg-ink-50 p-4">
            <legend class="sr-only">Carteira de habilitação</legend>
            <p class="text-sm font-medium text-ink-800">Carteira de habilitação (CNH)</p>

            <div>
                <label class="field-label" for="employee-cnh-number">Número de registro</label>
                <input
                    id="employee-cnh-number"
                    v-model="form.cnh_number"
                    data-test="cnh_number"
                    class="field-input tabular-nums"
                    inputmode="numeric"
                    maxlength="11"
                    autocomplete="off"
                    :aria-invalid="Boolean(errors.cnh_number)"
                />
                <p v-if="errors.cnh_number" data-test="cnh_number-error" class="field-error">{{ errors.cnh_number }}</p>
                <p v-else class="field-hint">11 dígitos.</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label" for="employee-cnh-category">Categoria</label>
                    <select
                        id="employee-cnh-category"
                        v-model="form.cnh_category"
                        data-test="cnh_category"
                        class="field-input"
                        :aria-invalid="Boolean(errors.cnh_category)"
                    >
                        <option value="" disabled>Selecione</option>
                        <option v-for="category in CNH_CATEGORIES" :key="category" :value="category">{{ category }}</option>
                    </select>
                    <p v-if="errors.cnh_category" data-test="cnh_category-error" class="field-error">{{ errors.cnh_category }}</p>
                </div>

                <div>
                    <label class="field-label" for="employee-cnh-expiration">Validade</label>
                    <input
                        id="employee-cnh-expiration"
                        v-model="form.cnh_expiration_date"
                        data-test="cnh_expiration_date"
                        class="field-input tabular-nums"
                        type="date"
                        :min="todayIso()"
                        :aria-invalid="Boolean(errors.cnh_expiration_date)"
                    />
                </div>
            </div>
            <p v-if="errors.cnh_expiration_date" data-test="cnh_expiration_date-error" class="field-error !mt-2">
                {{ errors.cnh_expiration_date }}
            </p>
        </fieldset>

        <fieldset>
            <legend class="field-label">Status</legend>
            <div class="mt-1.5 grid grid-cols-3 gap-2">
                <label
                    v-for="status in EMPLOYEE_STATUSES"
                    :key="status"
                    class="flex h-11 cursor-pointer items-center justify-center rounded-md border px-2 text-center text-sm font-medium transition-colors"
                    :class="choiceClass(form.status === status)"
                >
                    <input v-model="form.status" type="radio" name="employee-status" class="sr-only" :value="status" :data-test="`status-${status}`" />
                    {{ EMPLOYEE_STATUS_LABELS[status] }}
                </label>
            </div>
            <p v-if="errors.status" data-test="status-error" class="field-error">{{ errors.status }}</p>
        </fieldset>

        <div class="flex justify-end gap-3 border-t border-ink-100 pt-5">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Salvando…' : 'Cadastrar funcionário' }}
            </button>
        </div>
    </form>
</template>
