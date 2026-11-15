<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue';

withDefaults(defineProps<{ title: string; description?: string; icon?: IconName }>(), { icon: 'info' });
</script>

<template>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-ink-100 text-ink-500">
            <AppIcon :name="icon" />
        </span>
        <h2 class="mt-4 font-display text-base font-semibold text-ink-900">{{ title }}</h2>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-ink-500">{{ description }}</p>
        <!-- Ação principal do estado vazio (um botão), quando há algo que o usuário pode fazer. -->
        <div v-if="$slots.default" class="mt-5 flex flex-wrap justify-center gap-3">
            <slot />
        </div>
    </div>
</template>
