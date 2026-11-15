<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import { USER_ROLE_LABELS, type Permission } from '@/modules/auth/types/auth';
import AppIcon, { type IconName } from './AppIcon.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

interface Section {
    name: string;
    label: string;
    icon: IconName;
    permission?: Permission;
}

const groups: { label: string; sections: Section[] }[] = [
    {
        label: 'Operação',
        sections: [
            { name: 'dashboard', label: 'Painel', icon: 'panel' },
            { name: 'fleet', label: 'Frota', icon: 'bus', permission: 'fleet.view' },
            { name: 'operations', label: 'Operações', icon: 'route', permission: 'operations.view' },
            { name: 'crew', label: 'Tripulação', icon: 'crew', permission: 'crew.view' },
            { name: 'maintenance', label: 'Manutenção', icon: 'wrench', permission: 'maintenance.view' },
        ],
    },
    {
        label: 'Controle',
        sections: [{ name: 'audit', label: 'Auditoria', icon: 'shield', permission: 'audit.view' }],
    },
];

// Só aparecem as áreas que o papel do usuário pode abrir; grupo sem área some inteiro.
const visibleGroups = computed(() =>
    groups
        .map((group) => ({ ...group, sections: group.sections.filter((section) => section.permission === undefined || auth.can(section.permission)) }))
        .filter((group) => group.sections.length > 0),
);

const initials = computed(() => {
    const parts = (auth.user?.name ?? '').trim().split(/\s+/).filter(Boolean);

    return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? (parts.at(-1)?.[0] ?? '') : '')).toUpperCase();
});

const today = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', month: 'short' }).format(new Date());

// No celular a navegação é uma gaveta; fecha sozinha ao trocar de tela.
const menuOpen = ref(false);
watch(
    () => route.fullPath,
    () => {
        menuOpen.value = false;
    },
);

async function signOut(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen lg:pl-64">
        <div v-if="menuOpen" class="fixed inset-0 z-30 bg-ink-950/50 backdrop-blur-xs lg:hidden" data-test="menu-backdrop" @click="menuOpen = false" />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-ink-950 text-ink-300 transition-transform duration-200 lg:translate-x-0"
            :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
            data-test="sidebar"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 px-5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-signal-500 text-ink-950">
                    <AppIcon name="bus" />
                </span>
                <span class="font-display text-base font-semibold tracking-tight text-white">ERP Viação</span>
            </div>

            <nav aria-label="Seções" class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                <div v-for="group in visibleGroups" :key="group.label">
                    <p class="px-3 pb-1.5 text-xs font-medium text-ink-500">{{ group.label }}</p>
                    <div class="space-y-0.5">
                        <RouterLink
                            v-for="section in group.sections"
                            :key="section.name"
                            :to="{ name: section.name }"
                            class="nav-link"
                            exact-active-class="nav-link-active"
                            :data-test="`nav-${section.name}`"
                        >
                            <AppIcon :name="section.icon" />
                            {{ section.label }}
                        </RouterLink>
                    </div>
                </div>
            </nav>

            <div v-if="auth.user" class="shrink-0 border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white ring-1 ring-inset ring-white/15"
                        data-test="user-initials"
                        aria-hidden="true"
                    >
                        {{ initials }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-white" data-test="user-name">{{ auth.user.name }}</p>
                        <p class="truncate text-xs text-ink-400" data-test="user-role">{{ USER_ROLE_LABELS[auth.user.role] }}</p>
                    </div>
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-ink-400 transition-colors hover:bg-white/10 hover:text-white"
                        data-test="sign-out"
                        aria-label="Sair"
                        title="Sair"
                        @click="signOut"
                    >
                        <AppIcon name="logout" />
                    </button>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-ink-200/90 bg-white/85 px-4 backdrop-blur sm:px-8">
            <button
                type="button"
                class="icon-btn -ml-2 lg:hidden"
                data-test="menu-toggle"
                aria-label="Abrir navegação"
                :aria-expanded="menuOpen"
                @click="menuOpen = !menuOpen"
            >
                <AppIcon name="menu" />
            </button>
            <p class="min-w-0 truncate text-sm">
                <span class="text-ink-400">ERP Viação</span>
                <span class="mx-2 text-ink-300" aria-hidden="true">/</span>
                <span class="font-medium text-ink-900" data-test="header-title">{{ route.meta.title }}</span>
            </p>
            <p class="num ml-auto hidden text-xs text-ink-500 sm:block">{{ today }}</p>
        </header>

        <main class="px-4 py-6 sm:px-8 lg:py-8">
            <div class="mx-auto max-w-7xl">
                <RouterView />
            </div>
        </main>
    </div>
</template>
