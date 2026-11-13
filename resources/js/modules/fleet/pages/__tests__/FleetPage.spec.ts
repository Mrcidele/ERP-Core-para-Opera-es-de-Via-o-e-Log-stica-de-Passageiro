import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Vehicle } from '../../types/vehicle';
import FleetPage from '../FleetPage.vue';

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
});

const fleet = [make('1', 'AAA1A11', 'active'), make('2', 'BBB2B22', 'maintenance'), make('3', 'CCC3C33', 'active')];

async function mountPage(vehicles: Vehicle[] = fleet): Promise<VueWrapper> {
    http.get.mockResolvedValue({ data: { data: vehicles } });
    const wrapper = mount(FleetPage);
    await flushPromises();

    return wrapper;
}

const plates = (wrapper: VueWrapper): string[] =>
    wrapper.findAll('[data-test="vehicle-row"] .plate').map((plate) => plate.text());

describe('FleetPage', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    it('loads and lists the vehicles with counts per status', async () => {
        const wrapper = await mountPage();

        expect(http.get).toHaveBeenCalledWith('/vehicles');
        expect(plates(wrapper)).toEqual(['AAA1A11', 'BBB2B22', 'CCC3C33']);
        expect(wrapper.get('[data-test="filter-all"]').text()).toContain('3');
        expect(wrapper.get('[data-test="filter-maintenance"]').text()).toContain('1');
    });

    it('filters by status and by search', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-test="filter-active"]').trigger('click');
        expect(plates(wrapper)).toEqual(['AAA1A11', 'CCC3C33']);

        await wrapper.get('[data-test="search"]').setValue('ccc-3');
        expect(plates(wrapper)).toEqual(['CCC3C33']);

        await wrapper.get('[data-test="search"]').setValue('QQQ');
        expect(wrapper.find('[data-test="no-results"]').exists()).toBe(true);
    });

    it('shows the empty state when there are no vehicles', async () => {
        const wrapper = await mountPage([]);

        expect(wrapper.find('[data-test="empty"]').exists()).toBe(true);
    });

    it('shows an error state when loading fails', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const wrapper = mount(FleetPage);
        await flushPromises();

        expect(wrapper.find('[data-test="list-error"]').exists()).toBe(true);
    });

    it('creates a vehicle from the drawer and adds it to the list in plate order', async () => {
        const created = make('4', 'ABC1D23', 'active');
        http.post.mockResolvedValue({ data: { data: created } });
        const wrapper = await mountPage();

        await wrapper.get('[data-test="new-vehicle"]').trigger('click');
        await wrapper.get('[data-test="plate"]').setValue('ABC1D23');
        await wrapper.get('[data-test="chassis"]').setValue('9BWZZZ377VT004254');
        await wrapper.get('[data-test="capacity"]').setValue(46);
        await wrapper.get('[data-test="class_type"]').setValue('executivo');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(plates(wrapper)).toEqual(['AAA1A11', 'ABC1D23', 'BBB2B22', 'CCC3C33']);
        expect(wrapper.get('[data-test="notice"]').text()).toContain('Veículo ABC1D23 cadastrado.');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('edits a vehicle from the drawer', async () => {
        http.put.mockResolvedValue({ data: { data: { ...fleet[1], status: 'active' } } });
        const wrapper = await mountPage();

        await wrapper.findAll('[data-test="edit"]')[1]!.trigger('click');
        expect((wrapper.get('[data-test="plate"]').element as HTMLInputElement).value).toBe('BBB2B22');

        await wrapper.get('[data-test="status-active"]').setValue();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.put).toHaveBeenCalledWith('/vehicles/2', expect.objectContaining({ status: 'active' }));
        expect(wrapper.get('[data-test="filter-maintenance"]').text()).toContain('0');
        expect(wrapper.get('[data-test="notice"]').text()).toContain('Veículo BBB2B22 atualizado.');
    });

    it('deletes a vehicle only after confirmation', async () => {
        http.delete.mockResolvedValue({});
        const wrapper = await mountPage();

        await wrapper.findAll('[data-test="remove"]')[0]!.trigger('click');
        expect(http.delete).not.toHaveBeenCalled();

        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.delete).toHaveBeenCalledWith('/vehicles/1');
        expect(plates(wrapper)).toEqual(['BBB2B22', 'CCC3C33']);
        expect(wrapper.get('[data-test="notice"]').text()).toContain('Veículo AAA1A11 excluído.');
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
});
