import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';

declare module 'vue-router' {
    interface RouteMeta {
        title: string;
    }
}

export const routes: RouteRecordRaw[] = [
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
        meta: { title: 'Frota' },
    },
    {
        path: '/operacoes',
        name: 'operations',
        component: () => import('@/modules/operations/pages/OperationsPage.vue'),
        meta: { title: 'Operações' },
    },
    {
        path: '/tripulacao',
        name: 'crew',
        component: () => import('@/modules/crew/pages/CrewPage.vue'),
        meta: { title: 'Tripulação' },
    },
    {
        path: '/manutencao',
        name: 'maintenance',
        component: () => import('@/modules/maintenance/pages/MaintenancePage.vue'),
        meta: { title: 'Manutenção' },
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
