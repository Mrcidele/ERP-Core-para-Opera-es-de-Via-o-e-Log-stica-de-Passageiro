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
        component: () => import('@/modules/dashboard/pages/DashboardPage.vue'),
        meta: { title: 'Painel' },
    },
    {
        path: '/frota',
        name: 'fleet',
        component: () => import('@/modules/fleet/pages/FleetPage.vue'),
        meta: { title: 'Frota', permission: 'fleet.view' },
    },
    {
        path: '/operacoes',
        name: 'operations',
        component: () => import('@/modules/operations/pages/OperationsPage.vue'),
        meta: { title: 'Operações', permission: 'operations.view' },
    },
    {
        path: '/tripulacao',
        name: 'crew',
        component: () => import('@/modules/crew/pages/CrewPage.vue'),
        meta: { title: 'Tripulação', permission: 'crew.view' },
    },
    {
        path: '/manutencao',
        name: 'maintenance',
        component: () => import('@/modules/maintenance/pages/MaintenancePage.vue'),
        meta: { title: 'Manutenção', permission: 'maintenance.view' },
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
