import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Employee } from '../../types/employee';
import EmployeeForm from '../EmployeeForm.vue';

const http = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const created: Employee = {
    id: 'uuid-1',
    name: 'João da Silva',
    cpf: '52998224725',
    role: 'driver',
    status: 'active',
    cnh_number: '12345678901',
    cnh_category: 'D',
    cnh_expiration_date: '2099-06-30',
};

async function fillBase(wrapper: VueWrapper, role: string): Promise<void> {
    await wrapper.get('[data-test="name"]').setValue('João da Silva');
    await wrapper.get('[data-test="cpf"]').setValue('52998224725');
    await wrapper.get(`[data-test="role-${role}"]`).setValue();
}

async function fillCnh(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('[data-test="cnh_number"]').setValue('12345678901');
    await wrapper.get('[data-test="cnh_category"]').setValue('D');
    await wrapper.get('[data-test="cnh_expiration_date"]').setValue('2099-06-30');
}

async function submit(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

describe('EmployeeForm', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.post.mockReset();
    });

    it('shows the license fields only for drivers', async () => {
        const wrapper = mount(EmployeeForm);

        expect(wrapper.find('[data-test="cnh-fields"]').exists()).toBe(false);

        await wrapper.get('[data-test="role-driver"]').setValue();
        expect(wrapper.find('[data-test="cnh-fields"]').exists()).toBe(true);

        await wrapper.get('[data-test="role-mechanic"]').setValue();
        expect(wrapper.find('[data-test="cnh-fields"]').exists()).toBe(false);
    });

    it('masks the CPF while typing', async () => {
        const wrapper = mount(EmployeeForm);

        await wrapper.get('[data-test="cpf"]').setValue('52998224725');

        expect((wrapper.get('[data-test="cpf"]').element as HTMLInputElement).value).toBe('529.982.247-25');
    });

    it('shows client validation errors and does not call the API', async () => {
        const wrapper = mount(EmployeeForm);

        await submit(wrapper);

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.find('[data-test="name-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="cpf-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="role-error"]').exists()).toBe(true);
    });

    it('requires the license data for a driver', async () => {
        const wrapper = mount(EmployeeForm);

        await fillBase(wrapper, 'driver');
        await submit(wrapper);

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="cnh_number-error"]').text()).toContain('Informe o número da CNH');
        expect(wrapper.find('[data-test="cnh_category-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="cnh_expiration_date-error"]').exists()).toBe(true);
    });

    it('rejects an expired license before calling the API', async () => {
        const wrapper = mount(EmployeeForm);

        await fillBase(wrapper, 'driver');
        await fillCnh(wrapper);
        await wrapper.get('[data-test="cnh_expiration_date"]').setValue('2020-01-01');
        await submit(wrapper);

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="cnh_expiration_date-error"]').text()).toContain('vencida');
    });

    it('posts a driver with the license data and emits the saved employee', async () => {
        http.post.mockResolvedValue({ data: { data: created } });
        const wrapper = mount(EmployeeForm);

        await fillBase(wrapper, 'driver');
        await fillCnh(wrapper);
        await submit(wrapper);

        expect(http.post).toHaveBeenCalledWith('/crew/employees', {
            name: 'João da Silva',
            cpf: '52998224725',
            role: 'driver',
            status: 'active',
            cnh_number: '12345678901',
            cnh_category: 'D',
            cnh_expiration_date: '2099-06-30',
        });
        expect(wrapper.emitted('saved')).toEqual([[created]]);
    });

    it('does not send license data typed before switching away from driver', async () => {
        http.post.mockResolvedValue({ data: { data: { ...created, role: 'mechanic' } } });
        const wrapper = mount(EmployeeForm);

        await fillBase(wrapper, 'driver');
        await fillCnh(wrapper);
        await wrapper.get('[data-test="role-mechanic"]').setValue();
        await wrapper.get('[data-test="status-on_leave"]').setValue();
        await submit(wrapper);

        expect(http.post).toHaveBeenCalledWith('/crew/employees', {
            name: 'João da Silva',
            cpf: '52998224725',
            role: 'mechanic',
            status: 'on_leave',
            cnh_number: null,
            cnh_category: null,
            cnh_expiration_date: null,
        });
    });

    it('shows validation errors returned by the API', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { cpf: ['Já existe um funcionário cadastrado com este CPF.'] } } },
        });
        const wrapper = mount(EmployeeForm);

        await fillBase(wrapper, 'admin');
        await submit(wrapper);

        expect(wrapper.get('[data-test="cpf-error"]').text()).toContain('Já existe um funcionário');
        expect(wrapper.emitted('saved')).toBeUndefined();
    });

    it('shows a generic message on unexpected failures', async () => {
        http.post.mockRejectedValue(new Error('network'));
        const wrapper = mount(EmployeeForm);

        await fillBase(wrapper, 'admin');
        await submit(wrapper);

        expect(wrapper.get('[data-test="error"]').text()).toContain('Não foi possível cadastrar');
    });
});
