<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { onlyDigits } from '@/core/utils/cpf';
import { countLabel } from '@/core/utils/format';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import EmployeeForm from '../components/EmployeeForm.vue';
import EmployeeTable from '../components/EmployeeTable.vue';
import { useEmployeeStore } from '../stores/employeeStore';
import { EMPLOYEE_ROLE_LABELS, EMPLOYEE_STATUS_LABELS, EMPLOYEE_STATUSES, type Employee, type EmployeeStatus } from '../types/employee';

const store = useEmployeeStore();
const auth = useAuthStore();
const toast = useToast();

// Sem crew.manage a tela é só de consulta.
const canManage = computed(() => auth.can('crew.manage'));

const statusFilter = ref<EmployeeStatus | 'all'>('all');
const search = ref('');
const formOpen = ref(false);

onMounted(() => store.fetchAll());

const filters = computed(() => [
    { value: 'all' as const, label: 'Todos', count: store.employees.length },
    ...EMPLOYEE_STATUSES.map((status) => ({
        value: status,
        label: EMPLOYEE_STATUS_LABELS[status],
        count: store.employees.filter((employee) => employee.status === status).length,
    })),
]);

const visibleEmployees = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('pt-BR');
    const digits = onlyDigits(search.value);

    return store.employees.filter(
        (employee) =>
            (statusFilter.value === 'all' || employee.status === statusFilter.value) &&
            (term === '' || employee.name.toLocaleLowerCase('pt-BR').includes(term) || (digits !== '' && employee.cpf.includes(digits))),
    );
});

const filtering = computed(() => statusFilter.value !== 'all' || search.value.trim() !== '');

function clearFilters(): void {
    statusFilter.value = 'all';
    search.value = '';
}

function onSaved(employee: Employee): void {
    toast.success(`${employee.name} cadastrado como ${EMPLOYEE_ROLE_LABELS[employee.role].toLowerCase()}.`);
    formOpen.value = false;
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Tripulação</h1>
            <p class="mt-1 text-sm text-ink-500">Motoristas, mecânicos e equipe administrativa, com a validade da CNH.</p>
        </div>
        <button v-if="canManage" type="button" class="btn btn-primary" data-test="new-employee" @click="formOpen = true">
            <AppIcon name="plus" />
            Cadastrar funcionário
        </button>
    </header>

    <section class="card mt-6 overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-ink-200 p-3">
            <div role="group" aria-label="Filtrar por status" class="segmented">
                <button
                    v-for="filter in filters"
                    :key="filter.value"
                    type="button"
                    :data-test="`filter-${filter.value}`"
                    :aria-pressed="statusFilter === filter.value"
                    @click="statusFilter = filter.value"
                >
                    {{ filter.label }}
                    <span class="num ml-1 text-xs text-ink-400">{{ filter.count }}</span>
                </button>
            </div>

            <label class="relative ml-auto block w-full sm:w-72">
                <span class="sr-only">Buscar por nome ou CPF</span>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-400"><AppIcon name="search" /></span>
                <input v-model="search" type="search" data-test="search" class="field-input !mt-0 !h-9 pl-10" placeholder="Buscar por nome ou CPF" />
            </label>
        </div>

        <TableSkeleton v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" :columns="6" label="Carregando funcionários" />

        <EmptyState
            v-else-if="store.listStatus === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar a tripulação"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchAll()">Tentar novamente</button>
        </EmptyState>

        <EmptyState
            v-else-if="store.employees.length === 0"
            data-test="empty"
            icon="crew"
            title="Nenhum funcionário cadastrado"
            :description="canManage ? 'Cadastre motoristas e mecânicos para escalar viagens e abrir ordens de serviço.' : 'Ainda não há funcionários cadastrados.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="formOpen = true">Cadastrar funcionário</button>
        </EmptyState>

        <EmptyState v-else-if="visibleEmployees.length === 0" data-test="no-results" icon="search" title="Nenhum funcionário corresponde ao filtro">
            <button type="button" class="btn btn-quiet" @click="clearFilters">Limpar filtros</button>
        </EmptyState>

        <template v-else>
            <EmployeeTable :employees="visibleEmployees" />
            <p class="border-t border-ink-200 px-5 py-2.5 text-xs text-ink-500">
                <template v-if="filtering">{{ visibleEmployees.length }} de {{ countLabel(store.employees.length, 'funcionário', 'funcionários') }}</template>
                <template v-else>{{ countLabel(store.employees.length, 'funcionário', 'funcionários') }}</template>
            </p>
        </template>
    </section>

    <SlideOver :open="formOpen" title="Cadastrar funcionário" subtitle="Motoristas precisam de CNH válida." @close="formOpen = false">
        <EmployeeForm @saved="onSaved" @cancel="formOpen = false" />
    </SlideOver>
</template>
