import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ToastContainer from '../ToastContainer.vue';
import { clearToasts, useToast } from '../useToast';

describe('useToast', () => {
    beforeEach(() => vi.useFakeTimers());

    afterEach(() => {
        clearToasts();
        vi.useRealTimers();
    });

    it('queues success, error and warning toasts with their tone and optional title', () => {
        const toast = useToast();

        toast.success('Veículo ABC1D23 cadastrado.');
        toast.error('Você não tem permissão para executar esta ação.', 'Acesso negado');
        toast.warning('Entre de novo para continuar.', 'Sua sessão terminou');

        expect(toast.toasts.value.map(({ tone, title, message }) => ({ tone, title, message }))).toEqual([
            { tone: 'success', title: null, message: 'Veículo ABC1D23 cadastrado.' },
            { tone: 'error', title: 'Acesso negado', message: 'Você não tem permissão para executar esta ação.' },
            { tone: 'warning', title: 'Sua sessão terminou', message: 'Entre de novo para continuar.' },
        ]);
    });

    it('shares one queue across every caller', () => {
        useToast().success('Primeiro');

        expect(useToast().toasts.value).toHaveLength(1);
    });

    it('shows identical toasts only once', () => {
        const toast = useToast();

        toast.error('Acesso negado.', 'Acesso negado');
        toast.error('Acesso negado.', 'Acesso negado');
        toast.error('Acesso negado.', 'Conflito');
        toast.success('Acesso negado.');

        expect(toast.toasts.value).toHaveLength(3);
    });

    it('dismisses a success toast after 5 seconds and keeps an error for 8', () => {
        const toast = useToast();

        toast.success('Salvo.');
        toast.error('Falhou.');

        vi.advanceTimersByTime(5000);
        expect(toast.toasts.value.map((item) => item.message)).toEqual(['Falhou.']);

        vi.advanceTimersByTime(3000);
        expect(toast.toasts.value).toEqual([]);
    });

    it('dismisses one toast by id', () => {
        const toast = useToast();

        toast.success('Um');
        toast.success('Dois');
        toast.dismiss(toast.toasts.value[0]!.id);

        expect(toast.toasts.value.map((item) => item.message)).toEqual(['Dois']);
    });

    it('clears everything', () => {
        useToast().success('Um');
        useToast().error('Dois');

        clearToasts();

        expect(useToast().toasts.value).toEqual([]);
    });
});

describe('ToastContainer', () => {
    afterEach(() => clearToasts());

    it('renders the queue, announcing errors as alerts and successes as status', async () => {
        const wrapper = mount(ToastContainer);
        const toast = useToast();

        toast.success('Viagem agendada.');
        toast.error('Você não tem permissão para executar esta ação.', 'Acesso negado');
        await wrapper.vm.$nextTick();

        const items = wrapper.findAll('[data-test="toast"]');

        expect(items.map((item) => item.attributes('role'))).toEqual(['status', 'alert']);
        expect(items.map((item) => item.attributes('data-tone'))).toEqual(['success', 'error']);
        expect(items[0]!.text()).toContain('Viagem agendada.');
        expect(items[1]!.get('[data-test="toast-title"]').text()).toBe('Acesso negado');
    });

    it('lets the user dismiss a toast', async () => {
        const wrapper = mount(ToastContainer);

        useToast().error('Falhou.');
        await wrapper.vm.$nextTick();
        await wrapper.get('button[aria-label="Dispensar aviso"]').trigger('click');

        expect(useToast().toasts.value).toEqual([]);
    });
});
