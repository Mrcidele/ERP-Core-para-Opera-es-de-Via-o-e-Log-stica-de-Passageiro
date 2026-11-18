import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { formatDateTime } from '@/core/utils/format';
import { signInAs } from '@/tests/auth';
import type { DashboardSummary } from '../../types/dashboard';
import DashboardView from '../DashboardView.vue';

const http = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const full: DashboardSummary = {
    generated_at: '2026-11-13T10:00:00+00:00',
    fleet: {
        total: 10,
        active: 8,
        maintenance: 1,
        inactive: 1,
        availability_rate: 80,
        preventive_maintenance_due: [
            { id: 'truck-1', plate: 'DDD4D44', current_mileage: 100_000, next_preventive_maintenance_km: 95_000, needs_preventive_maintenance: true },
        ],
    },
    operations: {
        in_progress_now: 2,
        scheduled_next_24h: 1,
        next_trips: [
            {
                id: 'trip-1',
                departure_time: '2026-11-13T11:00:00+00:00',
                arrival_time: '2026-11-13T17:30:00+00:00',
                line: { origin: 'Curitiba', destination: 'Ourinhos' },
                vehicle: { plate: 'ABC1D23' },
                driver: { name: 'João da Silva' },
            },
        ],
    },
    crew: {
        expiring_licenses: [
            { employee_id: 'd1', name: 'Carlos Vencido', cnh_expiration_date: '2026-11-10', days_remaining: -3 },
            { employee_id: 'd2', name: 'Bia Hoje', cnh_expiration_date: '2026-11-13', days_remaining: 0 },
            { employee_id: 'd3', name: 'Ana Limite', cnh_expiration_date: '2026-12-13', days_remaining: 30 },
        ],
    },
    maintenance: { completed_last_30_days: { count: 2, total_cost_cents: 145_100 }, open: 2, in_progress: 1 },
    fuel: { month_start: '2026-11-01T00:00:00+00:00', month_cost_cents: 2_150_590, month_liters_milliliters: 3_505_500, month_logs: 12 },
};

async function mountView(summary: DashboardSummary | Error): Promise<VueWrapper> {
    if (summary instanceof Error) {
        http.get.mockRejectedValue(summary);
    } else {
        http.get.mockResolvedValue({ data: { data: summary } });
    }

    const Blank = { template: '<div />' };
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', name: 'dashboard', component: Blank, meta: { title: 'Painel' } },
            { path: '/frota', name: 'fleet', component: Blank, meta: { title: 'Frota' } },
            { path: '/operacoes', name: 'operations', component: Blank, meta: { title: 'Operações' } },
        ],
    });
    const wrapper = mount(DashboardView, { global: { plugins: [router] } });
    await flushPromises();

    return wrapper;
}

const value = (wrapper: VueWrapper, name: string): string => wrapper.get(`[data-test="kpi-${name}"] [data-test="stat-value"]`).text();

const kpi = (wrapper: VueWrapper, name: string): string => wrapper.get(`[data-test="kpi-${name}"]`).text().replace(/\s+/g, ' ');

describe('DashboardView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        signInAs('admin');
        http.get.mockReset();
    });

    it('shows the four indicators with their context', async () => {
        const wrapper = await mountView(full);

        expect(http.get).toHaveBeenCalledWith('/dashboard/summary');
        expect(kpi(wrapper, 'availability')).toContain('80%');
        expect(kpi(wrapper, 'availability')).toContain('8 de 10 veículos ativos');
        expect(value(wrapper, 'in-progress')).toBe('2');
        expect(kpi(wrapper, 'in-progress')).toContain('viagens em andamento');
        expect(value(wrapper, 'next-24h')).toBe('1');
        expect(kpi(wrapper, 'next-24h')).toContain('viagem agendada');
        expect(kpi(wrapper, 'maintenance-cost')).toContain('R$ 1.451,00');
        expect(kpi(wrapper, 'maintenance-cost')).toContain('2 ordens concluídas');
        expect(wrapper.get('[data-test="kpi-maintenance-cost"] [data-test="stat-alert"]').text()).toBe('3 pendentes');
        expect(kpi(wrapper, 'fuel-cost')).toContain('R$ 21.505,90');
        expect(kpi(wrapper, 'fuel-cost')).toContain('3.505,5 L em 12 abastecimentos');
        expect(wrapper.get('[data-test="generated-at"]').text()).toContain(formatDateTime('2026-11-13T10:00:00+00:00'));
    });

    it('draws the fleet distribution in proportion', async () => {
        const wrapper = await mountView(full);

        expect(wrapper.get('[data-test="bar-active"]').attributes('style')).toContain('width: 80%');
        expect(wrapper.get('[data-test="bar-maintenance"]').attributes('style')).toContain('width: 10%');
        expect(wrapper.get('[data-test="bar-inactive"]').attributes('style')).toContain('width: 10%');
        expect(wrapper.get('[data-test="fleet-active"]').text()).toContain('8');
        expect(wrapper.get('[data-test="fleet-maintenance"]').text()).toContain('Em manutenção');
    });

    it('lists the next trips with local time, line, driver and plate', async () => {
        const wrapper = await mountView(full);
        const trip = wrapper.get('[data-test="next-trip"]');

        expect(trip.text()).toContain(formatDateTime('2026-11-13T11:00:00+00:00'));
        expect(trip.text()).toContain('Curitiba → Ourinhos');
        expect(trip.text()).toContain('João da Silva');
        expect(trip.get('.plate').text()).toBe('ABC1D23');
    });

    it('alerts about licenses expired, expiring today and expiring soon', async () => {
        const wrapper = await mountView(full);
        const alerts = wrapper.findAll('[data-test="license-alert"]').map((alert) => alert.text().replace(/\s+/g, ' '));

        expect(wrapper.get('[data-test="license-count"]').text()).toBe('3');
        expect(alerts[0]).toContain('Carlos Vencido');
        expect(alerts[0]).toContain('Vencida há 3 dias');
        expect(alerts[0]).toContain('10/11/2026');
        expect(alerts[1]).toContain('Vence hoje');
        expect(alerts[2]).toContain('Vence em 30 dias');
    });

    it('alerts about vehicles past the preventive maintenance mileage', async () => {
        const wrapper = await mountView(full);
        const alert = wrapper.get('[data-test="preventive-alert"]');

        expect(wrapper.get('[data-test="preventive-count"]').text()).toBe('1');
        expect(alert.get('.plate').text()).toBe('DDD4D44');
        expect(alert.text()).toContain('5.000 km além');
        expect(alert.text()).toContain('revisão aos 95.000 km');
    });

    it('says so when there is nothing to alert about', async () => {
        const wrapper = await mountView({
            ...full,
            fleet: { ...full.fleet!, preventive_maintenance_due: [] },
            operations: { in_progress_now: 0, scheduled_next_24h: 0, next_trips: [] },
            crew: { expiring_licenses: [] },
            maintenance: { completed_last_30_days: { count: 0, total_cost_cents: 0 }, open: 0, in_progress: 0 },
        });

        expect(wrapper.find('[data-test="license-ok"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="preventive-ok"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="next-trips-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="stat-alert"]').exists()).toBe(false);
        expect(value(wrapper, 'in-progress')).toBe('0');
    });

    it('handles an empty fleet without dividing by zero', async () => {
        const wrapper = await mountView({ ...full, fleet: { total: 0, active: 0, maintenance: 0, inactive: 0, availability_rate: 0, preventive_maintenance_due: [] } });

        expect(wrapper.find('[data-test="fleet-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="bar-active"]').exists()).toBe(false);
        expect(kpi(wrapper, 'availability')).toContain('0%');
    });

    it('shows only the blocks the API returned for the role', async () => {
        signInAs('mechanic_supervisor');
        const wrapper = await mountView({ ...full, operations: null, crew: null });

        expect(wrapper.find('[data-test="kpi-availability"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="kpi-maintenance-cost"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="kpi-fuel-cost"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="kpi-in-progress"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="kpi-next-24h"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="next-trips"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="license-alerts"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="preventive-alerts"]').exists()).toBe(true);
    });

    it('shows an error state with retry when the summary fails to load', async () => {
        const wrapper = await mountView(new Error('network'));

        expect(wrapper.get('[data-test="dashboard-error"]').text()).toContain('Não foi possível carregar o painel');

        http.get.mockResolvedValue({ data: { data: full } });
        await wrapper.get('[data-test="dashboard-error"] button').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-test="kpi-availability"]').exists()).toBe(true);
    });
});
