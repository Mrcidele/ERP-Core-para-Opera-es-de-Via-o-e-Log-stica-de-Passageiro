import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { page, pageParams } from '@/tests/paging';
import { signInAs } from '@/tests/auth';
import { lastToast } from '@/tests/toast';
import type { Vehicle } from '../../types/vehicle';
import FleetView from '../FleetView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const make = (id: string, plate: string, status: Vehicle['status']): Vehicle => ({
    id,
    plate,
    chassis: `9BWZZZ377VT00425${id}`,
    capacity: 46,
    class_type: 'executivo',
    status,
    current_mileage: 120000,
    next_preventive_maintenance_km: null,
    needs_preventive_maintenance: false,
});

const fleet = [make('1', 'AAA1A11', 'active'), make('2', 'BBB2B22', 'maintenance'), make('3', 'CCC3C33', 'active')];

async function mountPage(vehicles: Vehicle[] = fleet): Promise<VueWrapper> {
    http.get.mockResolvedValue(page(vehicles));
    const wrapper = mount(FleetView);
    await flushPromises();

    return wrapper;
}

const plates = (wrapper: VueWrapper): string[] =>
    wrapper.findAll('[data-test="vehicle-row"] .plate').map((plate) => plate.text());

describe('FleetView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        signInAs('admin');
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    afterEach(() => vi.useRealTimers());

    it('asks the API for the first page and lists what it returns with the total', async () => {
        http.get.mockResolvedValue(page(fleet, { total: 48, last_page: 4 }));
        const wrapper = mount(FleetView);
        await flushPromises();

        expect(http.get).toHaveBeenCalledWith('/fleet/vehicles', pageParams());
        expect(plates(wrapper)).toEqual(['AAA1A11', 'BBB2B22', 'CCC3C33']);
        expect(wrapper.get('[data-test="pagination-summary"]').text()).toBe('1 a 15 de 48 veículos');
    });

    it('changes page through the pagination bar', async () => {
        http.get.mockResolvedValue(page(fleet, { total: 48, last_page: 4 }));
        const wrapper = mount(FleetView);
        await flushPromises();

        http.get.mockResolvedValue(page([make('9', 'ZZZ9Z99', 'active')], { current_page: 2, total: 48, last_page: 4 }));
        await wrapper.get('[data-test="page-next"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/fleet/vehicles', pageParams({ page: 2 }));
        expect(plates(wrapper)).toEqual(['ZZZ9Z99']);
        expect(wrapper.get('[data-test="page-2"]').attributes('aria-current')).toBe('page');
    });

    it('filters by status and by search', async () => {
        vi.useFakeTimers();
        const wrapper = await mountPage();

        http.get.mockResolvedValue(page([fleet[0]!, fleet[2]!]));
        await wrapper.get('[data-test="filter-active"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/fleet/vehicles', pageParams({ status: 'active' }));
        expect(plates(wrapper)).toEqual(['AAA1A11', 'CCC3C33']);
        expect(wrapper.get('[data-test="filter-active"]').attributes('aria-pressed')).toBe('true');

        // A busca só vai ao servidor quando a digitação para.
        http.get.mockResolvedValue(page([]));
        await wrapper.get('[data-test="search"]').setValue('QQQ');
        expect(http.get).toHaveBeenCalledTimes(2);
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/fleet/vehicles', pageParams({ status: 'active', search: 'QQQ' }));
        expect(wrapper.find('[data-test="no-results"]').exists()).toBe(true);

        http.get.mockResolvedValue(page(fleet));
        await wrapper.get('[data-test="clear-filters"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/fleet/vehicles', pageParams());
        expect(plates(wrapper)).toHaveLength(3);
    });

    it('shows the empty state when there are no vehicles', async () => {
        const wrapper = await mountPage([]);

        expect(wrapper.find('[data-test="empty"]').exists()).toBe(true);
    });

    it('shows an error state when loading fails', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const wrapper = mount(FleetView);
        await flushPromises();

        expect(wrapper.find('[data-test="list-error"]').exists()).toBe(true);
    });

    it('creates a vehicle from the drawer and reloads the page from the API', async () => {
        const created = make('4', 'ABC1D23', 'active');
        http.post.mockResolvedValue({ data: { data: created } });
        const wrapper = await mountPage();
        http.get.mockResolvedValue(page([fleet[0]!, created, fleet[1]!, fleet[2]!]));

        await wrapper.get('[data-test="new-vehicle"]').trigger('click');
        await wrapper.get('[data-test="plate"]').setValue('ABC1D23');
        await wrapper.get('[data-test="chassis"]').setValue('9BWZZZ377VT004254');
        await wrapper.get('[data-test="capacity"]').setValue(46);
        await wrapper.get('[data-test="class_type"]').setValue('executivo');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(plates(wrapper)).toEqual(['AAA1A11', 'ABC1D23', 'BBB2B22', 'CCC3C33']);
        expect(lastToast()).toContain('Veículo ABC1D23 cadastrado.');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('edits a vehicle from the drawer', async () => {
        http.put.mockResolvedValue({ data: { data: { ...fleet[1], status: 'active' } } });
        const wrapper = await mountPage();
        http.get.mockResolvedValue(page([fleet[0]!, { ...fleet[1]!, status: 'active' }, fleet[2]!]));

        await wrapper.findAll('[data-test="edit"]')[1]!.trigger('click');
        expect((wrapper.get('[data-test="plate"]').element as HTMLInputElement).value).toBe('BBB2B22');

        await wrapper.get('[data-test="status-active"]').setValue();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.put).toHaveBeenCalledWith('/fleet/vehicles/2', expect.objectContaining({ status: 'active' }));
        expect(wrapper.findAll('[data-test="vehicle-row"]')[1]!.get('[data-test="status-badge"]').text()).toBe('Ativo');
        expect(lastToast()).toContain('Veículo BBB2B22 atualizado.');
    });

    it('deletes a vehicle only after confirmation', async () => {
        http.delete.mockResolvedValue({});
        const wrapper = await mountPage();
        http.get.mockResolvedValue(page([fleet[1]!, fleet[2]!]));

        await wrapper.findAll('[data-test="remove"]')[0]!.trigger('click');
        expect(http.delete).not.toHaveBeenCalled();

        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.delete).toHaveBeenCalledWith('/fleet/vehicles/1');
        expect(plates(wrapper)).toEqual(['BBB2B22', 'CCC3C33']);
        expect(lastToast()).toContain('Veículo AAA1A11 excluído.');
    });

    it('keeps the vehicle and shows the reason when deletion fails', async () => {
        http.delete.mockRejectedValue(new Error('network'));
        const wrapper = await mountPage();

        await wrapper.findAll('[data-test="remove"]')[0]!.trigger('click');
        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(plates(wrapper)).toHaveLength(3);
        expect(wrapper.get('[data-test="confirm-error"]').text()).toContain('Não foi possível excluir');
    });

    it('is read-only for a role without fleet.manage', async () => {
        signInAs('dispatcher');
        const wrapper = await mountPage();

        expect(plates(wrapper)).toHaveLength(3);
        expect(wrapper.find('[data-test="new-vehicle"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="edit"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="remove"]').exists()).toBe(false);
    });

    it('offers no way to create a vehicle in the empty state without fleet.manage', async () => {
        signInAs('mechanic_supervisor');
        const wrapper = await mountPage([]);

        expect(wrapper.get('[data-test="empty"]').findAll('button')).toHaveLength(0);
    });
});
