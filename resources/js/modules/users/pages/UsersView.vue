<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import ConfirmDialog from '@/core/ui/ConfirmDialog.vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import ListToolbar from '@/core/ui/ListToolbar.vue';
import PaginationBar from '@/core/ui/PaginationBar.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { useToast } from '@/core/ui/useToast';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import UserForm from '../components/UserForm.vue';
import UserTable from '../components/UserTable.vue';
import { useUserStore } from '../stores/userStore';
import { USER_STATUS_LABELS, USER_STATUSES, type ManagedUser } from '../types/user';

const store = useUserStore();
const auth = useAuthStore();
const toast = useToast();

const currentUserId = computed(() => auth.user?.id ?? null);

const formOpen = ref(false);
const editing = ref<ManagedUser | null>(null);
const toggling = ref<ManagedUser | null>(null);

onMounted(() => store.fetchAll());

const STATUS_OPTIONS = USER_STATUSES.map((status) => ({ value: status, label: USER_STATUS_LABELS[status] }));

function openCreate(): void {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(user: ManagedUser): void {
    editing.value = user;
    formOpen.value = true;
}

function onSaved(user: ManagedUser): void {
    toast.success(editing.value ? `Usuário ${user.name} atualizado.` : `Usuário ${user.name} criado.`);
    formOpen.value = false;
}

function askToggle(user: ManagedUser): void {
    store.resetToggleState();
    toggling.value = user;
}

async function confirmToggle(): Promise<void> {
    const user = toggling.value;

    if (!user) {
        return;
    }

    const updated = await store.setActive(user, !user.active);

    if (updated) {
        toast.success(updated.active ? `Acesso de ${updated.name} reativado.` : `Acesso de ${updated.name} desativado.`);
        toggling.value = null;
    }
}
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Usuários</h1>
            <p class="mt-1 text-sm text-ink-500">Quem entra no sistema e o que cada perfil pode fazer.</p>
        </div>
        <button type="button" class="btn btn-primary" data-test="new-user" @click="openCreate">
            <AppIcon name="plus" />
            Criar usuário
        </button>
    </header>

    <section class="card mt-6 overflow-hidden">
        <ListToolbar
            :statuses="STATUS_OPTIONS"
            :status="store.status"
            :search="store.search"
            search-label="Buscar por nome ou e-mail"
            @update:status="store.applyFilters({ status: $event })"
            @update:search="store.applyFilters({ search: $event })"
        />

        <TableSkeleton v-if="store.listStatus === 'loading' || store.listStatus === 'idle'" data-test="loading" :columns="5" label="Carregando usuários" />

        <EmptyState
            v-else-if="store.listStatus === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar os usuários"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchAll()">Tentar novamente</button>
        </EmptyState>

        <EmptyState v-else-if="store.users.length === 0 && !store.filtering" data-test="empty" icon="users" title="Nenhum usuário cadastrado">
            <button type="button" class="btn btn-primary" @click="openCreate">Criar usuário</button>
        </EmptyState>

        <EmptyState v-else-if="store.users.length === 0" data-test="no-results" icon="search" title="Nenhum usuário corresponde ao filtro">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="store.applyFilters({ search: '', status: null })">Limpar filtros</button>
        </EmptyState>

        <template v-else>
            <div :class="{ 'opacity-60': store.refreshing }" :aria-busy="store.refreshing">
                <UserTable :users="store.users" :current-user-id="currentUserId" @edit="openEdit" @toggle="askToggle" />
            </div>
            <PaginationBar :meta="store.meta" singular="usuário" plural="usuários" :busy="store.refreshing" @change="store.fetchAll($event)" />
        </template>
    </section>

    <SlideOver
        :open="formOpen"
        :title="editing ? `Editar ${editing.name}` : 'Criar usuário'"
        :subtitle="editing ? 'Altere os dados, o perfil ou redefina a senha.' : 'A pessoa entra com o e-mail e a senha definidos aqui.'"
        @close="formOpen = false"
    >
        <UserForm :key="editing?.id ?? 'new'" :user="editing" @saved="onSaved" @cancel="formOpen = false" />
    </SlideOver>

    <ConfirmDialog
        :open="toggling !== null"
        :title="toggling?.active ? `Desativar ${toggling.name}?` : `Reativar ${toggling?.name ?? ''}?`"
        :message="
            toggling?.active
                ? 'A pessoa perde o acesso agora e as sessões abertas são encerradas. O histórico e a auditoria continuam.'
                : 'A pessoa volta a entrar com o e-mail e a senha que já tinha.'
        "
        :confirm-label="toggling?.active ? 'Desativar acesso' : 'Reativar acesso'"
        :busy="store.toggleStatus === 'loading'"
        :error="store.toggleError"
        @confirm="confirmToggle"
        @cancel="toggling = null"
    />
</template>
