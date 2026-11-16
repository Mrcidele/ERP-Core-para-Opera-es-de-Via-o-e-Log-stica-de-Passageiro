import { defineStore } from 'pinia';
import { usePagedList, type RequestStatus } from '@/core/api/pagination';
import type { AuditLog } from '../types/auditLog';

export type { RequestStatus };

export const useAuditLogStore = defineStore('audit/logs', () => {
    // A trilha vem paginada do servidor, com busca por ação, entidade, usuário ou IP.
    const list = usePagedList<AuditLog>('/audit-logs');

    return {
        logs: list.items,
        meta: list.meta,
        search: list.search,
        status: list.listStatus,
        refreshing: list.refreshing,
        filtering: list.filtering,
        fetchLogs: list.fetchPage,
        applyFilters: list.applyFilters,
    };
});
