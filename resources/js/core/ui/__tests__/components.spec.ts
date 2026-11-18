import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import EmptyState from '../EmptyState.vue';
import MercosulPlate from '../MercosulPlate.vue';
import SlideOver from '../SlideOver.vue';
import StatCard from '../StatCard.vue';
import StatusBadge from '../StatusBadge.vue';
import TableSkeleton from '../TableSkeleton.vue';

describe('StatusBadge', () => {
    it.each([
        ['vehicle', 'active', 'Ativo', 'positive'],
        ['vehicle', 'maintenance', 'Em manutenção', 'attention'],
        ['vehicle', 'inactive', 'Inativo', 'neutral'],
        ['employee', 'on_leave', 'Afastado', 'attention'],
        ['trip', 'scheduled', 'Agendada', 'info'],
        ['trip', 'in_progress', 'Em andamento', 'attention'],
        ['trip', 'completed', 'Concluída', 'positive'],
        ['trip', 'canceled', 'Cancelada', 'danger'],
        ['maintenance', 'open', 'Aberta', 'attention'],
        ['maintenance', 'completed', 'Concluída', 'positive'],
        ['maintenance', 'canceled', 'Cancelada', 'neutral'],
    ] as const)('shows %s %s as "%s" with the %s tone', (kind, status, label, tone) => {
        const wrapper = mount(StatusBadge, { props: { kind, status } });

        expect(wrapper.text()).toBe(label);
        expect(wrapper.attributes('data-tone')).toBe(tone);
    });

    it('shows an unknown status as it came, in the neutral tone', () => {
        const wrapper = mount(StatusBadge, { props: { kind: 'trip', status: 'delayed' } });

        expect(wrapper.text()).toBe('delayed');
        expect(wrapper.attributes('data-tone')).toBe('neutral');
    });
});

describe('MercosulPlate', () => {
    it('shows the plate characters under the BRASIL band', () => {
        const wrapper = mount(MercosulPlate, { props: { plate: 'ABC1D23' } });

        expect(wrapper.get('.plate').text()).toBe('ABC1D23');
        expect(wrapper.text()).toContain('BRASIL');
        expect(wrapper.text()).toContain('BR');
        expect(wrapper.attributes('aria-label')).toBe('Placa ABC1D23');
        expect(wrapper.get('.plate').classes()).toContain('font-mono');
    });
});

describe('StatCard', () => {
    it('shows label, value and subtitle, without the alert by default', () => {
        const wrapper = mount(StatCard, { props: { label: 'Disponibilidade da frota', value: '80%', subtitle: '8 de 10 veículos ativos', icon: 'gauge' } });

        expect(wrapper.text()).toContain('Disponibilidade da frota');
        expect(wrapper.get('[data-test="stat-value"]').text()).toBe('80%');
        expect(wrapper.get('[data-test="stat-subtitle"]').text()).toBe('8 de 10 veículos ativos');
        expect(wrapper.find('[data-test="stat-alert"]').exists()).toBe(false);
    });

    it('shows the alert indicator when given', () => {
        const wrapper = mount(StatCard, { props: { label: 'Custo', value: 'R$ 0,00', icon: 'coins', alert: '3 pendentes' } });

        expect(wrapper.get('[data-test="stat-alert"]').text()).toBe('3 pendentes');
    });
});

describe('EmptyState', () => {
    it('shows title, description and the main action', () => {
        const wrapper = mount(EmptyState, {
            props: { title: 'Nenhum veículo cadastrado', description: 'Cadastre o primeiro caminhão.' },
            slots: { default: '<button>Cadastrar veículo</button>' },
        });

        expect(wrapper.text()).toContain('Nenhum veículo cadastrado');
        expect(wrapper.text()).toContain('Cadastre o primeiro caminhão.');
        expect(wrapper.get('button').text()).toBe('Cadastrar veículo');
    });

    it('renders no action area without an action', () => {
        expect(mount(EmptyState, { props: { title: 'Nada aqui' } }).findAll('button')).toHaveLength(0);
    });
});

describe('TableSkeleton', () => {
    it('announces loading and draws the requested rows', () => {
        const wrapper = mount(TableSkeleton, { props: { rows: 3, columns: 4, label: 'Carregando veículos' } });

        expect(wrapper.attributes('aria-busy')).toBe('true');
        expect(wrapper.text()).toBe('Carregando veículos');
        expect(wrapper.findAll('.animate-pulse')).toHaveLength(3);
    });
});

describe('SlideOver', () => {
    it('renders nothing while closed', () => {
        expect(mount(SlideOver, { props: { open: false, title: 'Editar' } }).find('[role="dialog"]').exists()).toBe(false);
    });

    it('shows title, subtitle, content and the footer, and closes by the button, the backdrop and Escape', async () => {
        const wrapper = mount(SlideOver, {
            props: { open: true, title: 'Ver alterações', subtitle: 'por Ana' },
            slots: { default: '<p data-test="body">conteúdo</p>', footer: '<button data-test="footer-action">Fechar</button>' },
            attachTo: document.body,
        });

        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Ver alterações');
        expect(wrapper.text()).toContain('por Ana');
        expect(wrapper.find('[data-test="body"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="footer-action"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="slide-over-backdrop"]').classes()).toContain('backdrop-blur-xs');

        await wrapper.get('button[aria-label="Fechar"]').trigger('click');
        await wrapper.get('[data-test="slide-over-backdrop"]').trigger('click');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('close')).toHaveLength(3);

        wrapper.unmount();
    });

    it('is wider in the lg size and has no footer without the slot', () => {
        const wrapper = mount(SlideOver, { props: { open: true, title: 'Diff', size: 'lg' } });

        expect(wrapper.get('[role="dialog"]').classes()).toContain('max-w-3xl');
        expect(wrapper.find('footer').exists()).toBe(false);
    });
});
