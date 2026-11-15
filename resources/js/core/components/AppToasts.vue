<script setup lang="ts">
import { useNotificationStore, type NotificationTone } from '@/core/stores/notificationStore';
import AppIcon from './AppIcon.vue';

const store = useNotificationStore();

const STYLES: Record<NotificationTone, string> = {
    danger: 'border-brake-600 bg-white text-brake-700',
    positive: 'border-road-600 bg-white text-road-800',
};
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4" aria-live="assertive">
        <p
            v-for="notification in store.notifications"
            :key="notification.id"
            role="alert"
            data-test="toast"
            class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-lg border-l-4 px-4 py-3 text-sm font-medium shadow-panel"
            :class="STYLES[notification.tone]"
        >
            <span class="flex-1">{{ notification.message }}</span>
            <button type="button" class="-mr-1 -mt-1 rounded p-1 text-ink-400 hover:text-ink-900" aria-label="Dispensar aviso" @click="store.dismiss(notification.id)">
                <AppIcon name="close" />
            </button>
        </p>
    </div>
</template>
