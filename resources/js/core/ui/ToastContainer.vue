<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue';
import { useToast, type ToastTone } from './useToast';

const { toasts, dismiss } = useToast();

const ICONS: Record<ToastTone, IconName> = { success: 'check', error: 'alert', warning: 'info' };

const ICON_STYLES: Record<ToastTone, string> = {
    success: 'bg-road-100 text-road-600',
    error: 'bg-brake-100 text-brake-600',
    warning: 'bg-signal-100 text-signal-700',
};
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-5 sm:items-end">
        <TransitionGroup
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :role="toast.tone === 'success' ? 'status' : 'alert'"
                data-test="toast"
                :data-tone="toast.tone"
                class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-ink-200 bg-white p-3.5 shadow-raised"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="ICON_STYLES[toast.tone]">
                    <AppIcon :name="ICONS[toast.tone]" />
                </span>
                <div class="min-w-0 flex-1 pt-0.5 text-sm">
                    <p v-if="toast.title" class="font-semibold text-ink-900" data-test="toast-title">{{ toast.title }}</p>
                    <p class="text-ink-600" :class="toast.title ? '' : 'font-medium text-ink-800'">{{ toast.message }}</p>
                </div>
                <button type="button" class="-mr-1 -mt-1 rounded-md p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-900" aria-label="Dispensar aviso" @click="dismiss(toast.id)">
                    <AppIcon name="close" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
