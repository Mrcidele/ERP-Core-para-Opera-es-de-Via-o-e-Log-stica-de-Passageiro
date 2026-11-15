import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '@/core/api/http';
import type { AuditLog } from '../types/auditLog';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

/** Quantos registros a tela pede; o máximo que a API aceita por consulta. */
export const AUDIT_LOG_LIMIT = 200;

export const useAuditLogStore = defineStore('audit/logs', () => {
    const logs = ref<AuditLog[]>([]);
    const status = ref<RequestStatus>('idle');

    async function fetchLogs(): Promise<void> {
        status.value = 'loading';

        try {
            const response = await http.get<{ data: AuditLog[] }>('/audit-logs', { params: { limit: AUDIT_LOG_LIMIT } });

            logs.value = response.data.data;
            status.value = 'success';
        } catch {
            status.value = 'error';
        }
    }

    return { logs, status, fetchLogs };
});
