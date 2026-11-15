import { defineStore } from 'pinia';
import { ref } from 'vue';

export type NotificationTone = 'danger' | 'positive';

export interface AppNotification {
    id: number;
    tone: NotificationTone;
    message: string;
}

const VISIBLE_FOR_MS = 7000;

/** Avisos globais (ex.: acesso negado), exibidos por AppToasts em qualquer tela. */
export const useNotificationStore = defineStore('core/notifications', () => {
    const notifications = ref<AppNotification[]>([]);
    let sequence = 0;

    function dismiss(id: number): void {
        notifications.value = notifications.value.filter((notification) => notification.id !== id);
    }

    function notify(message: string, tone: NotificationTone = 'danger'): void {
        // Várias requisições recusadas ao mesmo tempo geram um aviso só.
        if (notifications.value.some((notification) => notification.message === message)) {
            return;
        }

        const id = ++sequence;

        notifications.value = [...notifications.value, { id, tone, message }];
        setTimeout(() => dismiss(id), VISIBLE_FOR_MS);
    }

    return { notifications, notify, dismiss };
});
