<script setup lang="ts">
import { ref } from 'vue';
import AppIcon from '@/core/components/AppIcon.vue';
import SlideOver from '@/core/components/SlideOver.vue';
import EmployeeForm from '../components/EmployeeForm.vue';
import { EMPLOYEE_ROLE_LABELS, type Employee } from '../types/employee';

const formOpen = ref(false);
const notice = ref<string | null>(null);

function onSaved(employee: Employee): void {
    notice.value = `${employee.name} cadastrado como ${EMPLOYEE_ROLE_LABELS[employee.role].toLowerCase()}.`;
    formOpen.value = false;
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">Tripulação</h1>
            <p class="mt-1 text-ink-500">Motoristas, mecânicos e equipe administrativa.</p>
        </div>
        <button type="button" class="btn btn-primary" data-test="new-employee" @click="formOpen = true">
            <AppIcon name="plus" />
            Cadastrar funcionário
        </button>
    </header>

    <p
        v-if="notice"
        role="status"
        data-test="notice"
        class="mt-6 flex items-center justify-between gap-4 rounded-md bg-road-100 px-4 py-3 text-sm font-medium text-road-800"
    >
        {{ notice }}
        <button type="button" class="underline underline-offset-2" @click="notice = null">Dispensar</button>
    </p>

    <section class="mt-6 max-w-xl rounded-xl border border-dashed border-ink-200 bg-white p-8">
        <h2 class="font-display text-lg font-semibold">A lista de funcionários ainda não está disponível</h2>
        <p class="mt-2 text-ink-600">Por enquanto é possível cadastrar. Listagem, edição e exclusão chegam na próxima etapa.</p>
    </section>

    <SlideOver :open="formOpen" title="Cadastrar funcionário" @close="formOpen = false">
        <EmployeeForm @saved="onSaved" @cancel="formOpen = false" />
    </SlideOver>
</template>
