import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Vehicle } from '../../types/vehicle';
import VehicleForm from '../VehicleForm.vue';

const http = vi.hoisted(() => ({ post: vi.fn(), put: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const vehicle: Vehicle = {
    id: 'uuid-1',
    plate: 'ABC1D23',
    chassis: '9BWZZZ377VT004251',
    capacity: 46,
    class_type: 'executivo',
    status: 'active',
    current_mileage: 120000,
    next_preventive_maintenance_km: null,
    needs_preventive_maintenance: false,
};

async function fillAndSubmit(wrapper: VueWrapper, plate = 'abc-1d23'): Promise<void> {
    await wrapper.get('[data-test="plate"]').setValue(plate);
    await wrapper.get('[data-test="chassis"]').setValue('9BWZZZ377VT004251');
    await wrapper.get('[data-test="capacity"]').setValue(46);
    await wrapper.get('[data-test="class_type"]').setValue('executivo');
    await wrapper.get('[data-test="current_mileage"]').setValue(120000);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

describe('VehicleForm', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.post.mockReset();
        http.put.mockReset();
    });

    describe('create', () => {
        it('shows client validation errors and does not call the API', async () => {
            const wrapper = mount(VehicleForm);

            await wrapper.get('form').trigger('submit');
            await flushPromises();

            expect(http.post).not.toHaveBeenCalled();
            expect(wrapper.get('[data-test="plate-error"]').text()).toContain('ABC1234');
            expect(wrapper.find('[data-test="chassis-error"]').exists()).toBe(true);
            expect(wrapper.find('[data-test="capacity-error"]').exists()).toBe(true);
            expect(wrapper.find('[data-test="class_type-error"]').exists()).toBe(true);
            expect(wrapper.emitted('saved')).toBeUndefined();
        });

        it('has no status field', () => {
            expect(mount(VehicleForm).find('[data-test="status-active"]').exists()).toBe(false);
        });

        it('posts the normalized payload and emits the saved vehicle', async () => {
            http.post.mockResolvedValue({ data: { data: vehicle } });
            const wrapper = mount(VehicleForm);

            await fillAndSubmit(wrapper);

            expect(http.post).toHaveBeenCalledWith('/fleet/vehicles', {
                plate: 'ABC1D23',
                chassis: '9BWZZZ377VT004251',
                capacity: 46,
                class_type: 'executivo',
                current_mileage: 120000,
                next_preventive_maintenance_km: null,
            });
            expect(wrapper.emitted('saved')).toEqual([[vehicle]]);
        });

        it('shows validation errors returned by the API and keeps the form filled', async () => {
            http.post.mockRejectedValue({
                isAxiosError: true,
                response: { status: 422, data: { errors: { plate: ['Já existe um veículo cadastrado com a placa ABC1D23.'] } } },
            });
            const wrapper = mount(VehicleForm);

            await fillAndSubmit(wrapper);

            expect(wrapper.get('[data-test="plate-error"]').text()).toContain('Já existe um veículo');
            expect(wrapper.emitted('saved')).toBeUndefined();
            expect((wrapper.get('[data-test="plate"]').element as HTMLInputElement).value).toBe('abc-1d23');
        });

        it('shows a generic message on unexpected failures', async () => {
            http.post.mockRejectedValue(new Error('network'));
            const wrapper = mount(VehicleForm);

            await fillAndSubmit(wrapper);

            expect(wrapper.get('[data-test="error"]').text()).toContain('Não foi possível cadastrar');
        });
    });

    describe('edit', () => {
        it('starts filled with the vehicle data', () => {
            const wrapper = mount(VehicleForm, { props: { vehicle } });

            expect((wrapper.get('[data-test="plate"]').element as HTMLInputElement).value).toBe('ABC1D23');
            expect((wrapper.get('[data-test="capacity"]').element as HTMLInputElement).value).toBe('46');
            expect((wrapper.get('[data-test="status-active"]').element as HTMLInputElement).checked).toBe(true);
        });

        it('puts the changes, including status, and emits the saved vehicle', async () => {
            const updated: Vehicle = { ...vehicle, status: 'maintenance', current_mileage: 125000 };
            http.put.mockResolvedValue({ data: { data: updated } });
            const wrapper = mount(VehicleForm, { props: { vehicle } });

            await wrapper.get('[data-test="status-maintenance"]').setValue();
            await wrapper.get('[data-test="current_mileage"]').setValue(125000);
            await wrapper.get('form').trigger('submit');
            await flushPromises();

            expect(http.post).not.toHaveBeenCalled();
            expect(http.put).toHaveBeenCalledWith('/fleet/vehicles/uuid-1', {
                plate: 'ABC1D23',
                chassis: '9BWZZZ377VT004251',
                capacity: 46,
                class_type: 'executivo',
                status: 'maintenance',
                current_mileage: 125000,
                next_preventive_maintenance_km: null,
            });
            expect(wrapper.emitted('saved')).toEqual([[updated]]);
        });

        it('shows the mileage rule returned by the API', async () => {
            http.put.mockRejectedValue({
                isAxiosError: true,
                response: { status: 422, data: { errors: { current_mileage: ['A quilometragem não pode ser menor que a atual (120000 km).'] } } },
            });
            const wrapper = mount(VehicleForm, { props: { vehicle } });

            await wrapper.get('[data-test="current_mileage"]').setValue(100);
            await wrapper.get('form').trigger('submit');
            await flushPromises();

            expect(wrapper.get('[data-test="current_mileage-error"]').text()).toContain('não pode ser menor');
        });
    });

    it('emits cancel', async () => {
        const wrapper = mount(VehicleForm);

        await wrapper.get('[data-test="cancel"]').trigger('click');

        expect(wrapper.emitted('cancel')).toHaveLength(1);
    });
});
