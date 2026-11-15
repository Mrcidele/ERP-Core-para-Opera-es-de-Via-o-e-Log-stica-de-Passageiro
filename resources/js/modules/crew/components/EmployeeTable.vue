<script setup lang="ts">
import StatusBadge from '@/core/ui/StatusBadge.vue';
import { maskCpf } from '@/core/utils/cpf';
import { formatDate } from '@/core/utils/format';
import { EMPLOYEE_ROLE_LABELS, type Employee } from '../types/employee';

defineProps<{ employees: Employee[] }>();

const DAY_MS = 86_400_000;

/** Dias até o vencimento da CNH, contados a partir de hoje no fuso do navegador. */
function daysUntil(isoDate: string): number {
    const [year = 0, month = 1, day = 1] = isoDate.split('-').map(Number);
    const today = new Date();

    return Math.round((new Date(year, month - 1, day).getTime() - new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime()) / DAY_MS);
}

function licenseState(isoDate: string): 'expired' | 'expiring' | 'ok' {
    const days = daysUntil(isoDate);

    return days <= 0 ? 'expired' : days <= 30 ? 'expiring' : 'ok';
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[820px]">
            <thead>
                <tr>
                    <th scope="col">Nome</th>
                    <th scope="col">CPF</th>
                    <th scope="col">Cargo</th>
                    <th scope="col">CNH</th>
                    <th scope="col">Validade da CNH</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="employee in employees" :key="employee.id" data-test="employee-row">
                    <td class="whitespace-nowrap font-medium text-ink-900">{{ employee.name }}</td>
                    <td class="num whitespace-nowrap text-ink-600">{{ maskCpf(employee.cpf) }}</td>
                    <td class="whitespace-nowrap">{{ EMPLOYEE_ROLE_LABELS[employee.role] }}</td>
                    <td class="num whitespace-nowrap text-ink-600">
                        <template v-if="employee.cnh_number">{{ employee.cnh_number }} <span class="ml-1 rounded bg-ink-100 px-1.5 py-0.5 text-xs font-semibold text-ink-700">{{ employee.cnh_category }}</span></template>
                        <span v-else class="font-sans text-ink-400">Não se aplica</span>
                    </td>
                    <td class="whitespace-nowrap">
                        <template v-if="employee.cnh_expiration_date">
                            <span class="num" :data-test="`license-${licenseState(employee.cnh_expiration_date)}`" :class="{ 'font-semibold text-brake-700': licenseState(employee.cnh_expiration_date) === 'expired', 'font-semibold text-signal-700': licenseState(employee.cnh_expiration_date) === 'expiring' }">
                                {{ formatDate(employee.cnh_expiration_date) }}
                            </span>
                            <span v-if="licenseState(employee.cnh_expiration_date) === 'expired'" class="ml-2 text-xs font-medium text-brake-700">Vencida</span>
                            <span v-else-if="licenseState(employee.cnh_expiration_date) === 'expiring'" class="ml-2 text-xs font-medium text-signal-700">
                                Vence em {{ daysUntil(employee.cnh_expiration_date) }} dias
                            </span>
                        </template>
                        <span v-else class="text-ink-400">Não se aplica</span>
                    </td>
                    <td><StatusBadge kind="employee" :status="employee.status" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
