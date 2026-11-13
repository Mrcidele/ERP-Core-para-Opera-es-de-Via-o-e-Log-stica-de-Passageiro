import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import VehicleForm from '../VehicleForm.vue';

const post = vi.hoisted(() => vi.fn());

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http: { post },
}));

async function fillAndSubmit(wrapper: ReturnType<typeof mount>, plate = 'abc-1d23'): Promise<void> {
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
        post.mockReset();
    });

    it('shows client validation errors and does not call the API', async () => {
        const wrapper = mount(VehicleForm);

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="plate-error"]').text()).toContain('ABC1234');
        expect(wrapper.find('[data-test="chassis-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="capacity-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="class_type-error"]').exists()).toBe(true);
    });

    it('posts the normalized payload, shows success and resets the form', async () => {
        post.mockResolvedValue({
            data: {
                data: {
                    id: 'uuid',
                    plate: 'ABC1D23',
                    chassis: '9BWZZZ377VT004251',
                    capacity: 46,
                    class_type: 'executivo',
                    status: 'active',
                    current_mileage: 120000,
                },
            },
        });
        const wrapper = mount(VehicleForm);

        await fillAndSubmit(wrapper);

        expect(post).toHaveBeenCalledWith('/vehicles', {
            plate: 'ABC1D23',
            chassis: '9BWZZZ377VT004251',
            capacity: 46,
            class_type: 'executivo',
            current_mileage: 120000,
        });
        expect(wrapper.get('[data-test="success"]').text()).toContain('ABC1D23');
        expect((wrapper.get('[data-test="plate"]').element as HTMLInputElement).value).toBe('');
    });

    it('shows validation errors returned by the API and keeps the form filled', async () => {
        post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { plate: ['Já existe um veículo cadastrado com a placa ABC1D23.'] } } },
        });
        const wrapper = mount(VehicleForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.get('[data-test="plate-error"]').text()).toContain('Já existe um veículo');
        expect(wrapper.find('[data-test="success"]').exists()).toBe(false);
        expect((wrapper.get('[data-test="plate"]').element as HTMLInputElement).value).toBe('abc-1d23');
    });

    it('shows a generic message on unexpected failures', async () => {
        post.mockRejectedValue(new Error('network'));
        const wrapper = mount(VehicleForm);

        await fillAndSubmit(wrapper);

        expect(wrapper.get('[data-test="error"]').text()).toContain('Não foi possível');
    });
});
