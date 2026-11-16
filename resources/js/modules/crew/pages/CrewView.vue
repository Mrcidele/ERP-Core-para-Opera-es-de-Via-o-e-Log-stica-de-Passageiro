<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import ListToolbar from '@/core/ui/ListToolbar.vue';
import PaginationBar from '@/core/ui/PaginationBar.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import EmployeeForm from '../components/EmployeeForm.vue';
import EmployeeTable from '../components/EmployeeTable.vue';
import { useEmployeeStore } from '../stores/employeeStore';
import { EMPLOYEE_ROLE_LABELS, EMPLOYEE_STATUS_LABELS, EMPLOYEE_STATUSES, type Employee } from '../types/employee';

const store = useEmployeeStore();
const auth = useAuthStore();
const toast = useToast();

// Sem crew.manage a tela é só de consulta.
const canManage = computed(() => auth.can('crew.manage'));

const formOpen = ref(false);

onMounted(() => store.fetchAll());

const STATUS_OPTIONS = EMPLOYEE_STATUSES.map((status) => ({ value: status, label: EMPLOYEE_STATUS_LABELS[status] }));

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
        <ListToolbar
            :statuses="STATUS_OPTIONS"
            :status="store.status"
            :search="store.search"
            search-label="Buscar por nome ou CPF"
            @update:status="store.applyFilters({ status: $event })"
            @update:search="store.applyFilters({ search: $event })"
        />

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
            v-else-if="store.employees.length === 0 && !store.filtering"
            data-test="empty"
            icon="crew"
            title="Nenhum funcionário cadastrado"
            :description="canManage ? 'Cadastre motoristas e mecânicos para escalar viagens e abrir ordens de serviço.' : 'Ainda não há funcionários cadastrados.'"
        >
            <button v-if="canManage" type="button" class="btn btn-primary" @click="formOpen = true">Cadastrar funcionário</button>
        </EmptyState>

        <EmptyState v-else-if="store.employees.length === 0" data-test="no-results" icon="search" title="Nenhum funcionário corresponde ao filtro">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="store.applyFilters({ search: '', status: null })">Limpar filtros</button>
        </EmptyState>

        <template v-else>
            <div :class="{ 'opacity-60': store.refreshing }" :aria-busy="store.refreshing">
                <EmployeeTable :employees="store.employees" />
            </div>
            <PaginationBar :meta="store.meta" singular="funcionário" plural="funcionários" :busy="store.refreshing" @change="store.fetchAll($event)" />
        </template>
    </section>

    <SlideOver :open="formOpen" title="Cadastrar funcionário" subtitle="Motoristas precisam de CNH válida." @close="formOpen = false">
        <EmployeeForm @saved="onSaved" @cancel="formOpen = false" />
    </SlideOver>
</template>
