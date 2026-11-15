<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import AppIcon, { type IconName } from '@/core/components/AppIcon.vue';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import { USER_ROLE_LABELS, type Permission } from '@/modules/auth/types/auth';

const auth = useAuthStore();
const router = useRouter();

const sections: { name: string; label: string; icon: IconName; permission?: Permission }[] = [
    { name: 'dashboard', label: 'Painel', icon: 'panel' },
    { name: 'fleet', label: 'Frota', icon: 'bus', permission: 'fleet.view' },
    { name: 'operations', label: 'Operações', icon: 'route', permission: 'operations.view' },
    { name: 'crew', label: 'Tripulação', icon: 'crew', permission: 'crew.view' },
    { name: 'maintenance', label: 'Manutenção', icon: 'wrench', permission: 'maintenance.view' },
];

// Só aparecem as áreas que o papel do usuário pode abrir.
const visibleSections = computed(() => sections.filter((section) => section.permission === undefined || auth.can(section.permission)));

async function signOut(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen lg:flex">
        <aside class="bg-ink-900 text-ink-200 lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-60 lg:shrink-0 lg:flex-col">
            <div class="flex items-center gap-3 px-5 pb-2 pt-5 lg:pb-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-signal-500 text-ink-900">
                    <AppIcon name="bus" />
                </span>
                <span class="font-display text-lg font-semibold tracking-tight text-white">ERP Viação</span>
            </div>

            <nav aria-label="Seções" class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-1 lg:flex-col lg:overflow-visible">
                <RouterLink
                    v-for="section in visibleSections"
                    :key="section.name"
                    :to="{ name: section.name }"
                    class="nav-link"
                    exact-active-class="nav-link-active"
                    :data-test="`nav-${section.name}`"
                >
                    <AppIcon :name="section.icon" />
                    {{ section.label }}
                </RouterLink>
            </nav>

            <div v-if="auth.user" class="flex items-center justify-between gap-3 border-t border-ink-800 px-5 py-3 lg:py-4">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white" data-test="user-name">{{ auth.user.name }}</p>
                    <p class="truncate text-xs text-ink-300" data-test="user-role">{{ USER_ROLE_LABELS[auth.user.role] }}</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-md px-2.5 py-1.5 text-sm font-medium text-ink-200 transition-colors hover:bg-ink-800 hover:text-white"
                    data-test="sign-out"
                    @click="signOut"
                >
                    Sair
                </button>
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-6 sm:px-8 lg:px-10 lg:py-9">
            <div class="mx-auto max-w-6xl">
                <RouterView />
            </div>
        </main>
    </div>
</template>
