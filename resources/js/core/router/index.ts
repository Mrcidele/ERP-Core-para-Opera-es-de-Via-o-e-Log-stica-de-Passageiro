import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import type { Permission } from '@/modules/auth/types/auth';

declare module 'vue-router' {
    interface RouteMeta {
        title: string;
        /** Rota aberta a quem não entrou (só o login). As demais exigem sessão. */
        public?: boolean;
        /** Permissão exigida para abrir a rota. */
        permission?: Permission;
    }
}

export const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/modules/auth/pages/LoginView.vue'),
        meta: { title: 'Entrar', public: true },
    },
    {
        path: '/',
        name: 'dashboard',
        component: () => import('@/modules/dashboard/pages/DashboardView.vue'),
        meta: { title: 'Painel' },
    },
    {
        path: '/frota',
        name: 'fleet',
        component: () => import('@/modules/fleet/pages/FleetView.vue'),
        meta: { title: 'Frota', permission: 'fleet.view' },
    },
    {
        path: '/abastecimento',
        name: 'fuel',
        component: () => import('@/modules/fuel/pages/FuelView.vue'),
        meta: { title: 'Abastecimento', permission: 'fleet.view' },
    },
    {
        path: '/operacoes',
        name: 'operations',
        component: () => import('@/modules/operations/pages/OperationsView.vue'),
        meta: { title: 'Operações', permission: 'operations.view' },
    },
    {
        path: '/linhas',
        name: 'lines',
        component: () => import('@/modules/operations/pages/LinesView.vue'),
        meta: { title: 'Linhas e rotas', permission: 'operations.view' },
    },
    {
        path: '/tripulacao',
        name: 'crew',
        component: () => import('@/modules/crew/pages/CrewView.vue'),
        meta: { title: 'Tripulação', permission: 'crew.view' },
    },
    {
        path: '/manutencao',
        name: 'maintenance',
        component: () => import('@/modules/maintenance/pages/MaintenanceView.vue'),
        meta: { title: 'Manutenção', permission: 'maintenance.view' },
    },
    {
        path: '/audit-logs',
        name: 'audit',
        component: () => import('@/modules/audit/pages/AuditLogsView.vue'),
        meta: { title: 'Auditoria', permission: 'audit.view' },
    },
    {
        path: '/usuarios',
        name: 'users',
        component: () => import('@/modules/users/pages/UsersView.vue'),
        meta: { title: 'Usuários', permission: 'users.manage' },
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'dashboard' } },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.afterEach((to) => {
    document.title = `${to.meta.title} | ERP Viação`;
});
