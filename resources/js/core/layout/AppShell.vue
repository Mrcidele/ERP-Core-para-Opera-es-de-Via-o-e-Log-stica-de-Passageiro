<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router';
import AppIcon, { type IconName } from '@/core/components/AppIcon.vue';

const sections: { name: string; label: string; icon: IconName }[] = [
    { name: 'dashboard', label: 'Painel', icon: 'panel' },
    { name: 'fleet', label: 'Frota', icon: 'bus' },
    { name: 'operations', label: 'Operações', icon: 'route' },
    { name: 'crew', label: 'Tripulação', icon: 'crew' },
    { name: 'maintenance', label: 'Manutenção', icon: 'wrench' },
];
</script>

<template>
    <div class="min-h-screen lg:flex">
        <aside class="bg-ink-900 text-ink-200 lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:shrink-0">
            <div class="flex items-center gap-3 px-5 pb-2 pt-5 lg:pb-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-signal-500 text-ink-900">
                    <AppIcon name="bus" />
                </span>
                <span class="font-display text-lg font-semibold tracking-tight text-white">ERP Viação</span>
            </div>

            <nav aria-label="Seções" class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:overflow-visible">
                <RouterLink
                    v-for="section in sections"
                    :key="section.name"
                    :to="{ name: section.name }"
                    class="nav-link"
                    exact-active-class="nav-link-active"
                >
                    <AppIcon :name="section.icon" />
                    {{ section.label }}
                </RouterLink>
            </nav>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-6 sm:px-8 lg:px-10 lg:py-9">
            <div class="mx-auto max-w-6xl">
                <RouterView />
            </div>
        </main>
    </div>
</template>
