import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '@/core/api/http';
import type { DashboardSummary } from '../types/dashboard';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

export const useDashboardStore = defineStore('dashboard', () => {
    const summary = ref<DashboardSummary | null>(null);
    const status = ref<RequestStatus>('idle');

    async function fetchSummary(): Promise<void> {
        status.value = 'loading';

        try {
            const response = await http.get<{ data: DashboardSummary }>('/dashboard/summary');

            summary.value = response.data.data;
            status.value = 'success';
        } catch {
            status.value = 'error';
        }
    }

    return { summary, status, fetchSummary };
});
