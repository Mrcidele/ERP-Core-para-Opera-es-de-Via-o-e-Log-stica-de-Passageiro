import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ListToolbar from '../ListToolbar.vue';

const statuses = [
    { value: 'active', label: 'Ativo' },
    { value: 'inactive', label: 'Inativo' },
];

describe('ListToolbar', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('offers every status plus the option that clears the filter', async () => {
        const wrapper = mount(ListToolbar, { props: { statuses, status: 'active', allLabel: 'Todas', search: '', searchLabel: 'Buscar' } });

        expect(wrapper.findAll('[role="group"] button').map((button) => button.text())).toEqual(['Todas', 'Ativo', 'Inativo']);
        expect(wrapper.get('[data-test="filter-active"]').attributes('aria-pressed')).toBe('true');

        await wrapper.get('[data-test="filter-inactive"]').trigger('click');
        await wrapper.get('[data-test="filter-all"]').trigger('click');

        expect(wrapper.emitted('update:status')).toEqual([['inactive'], [null]]);
    });

    it('shows only the search when there are no statuses', () => {
        const wrapper = mount(ListToolbar, { props: { search: '', searchLabel: 'Buscar por usuário' } });

        expect(wrapper.find('[role="group"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="search"]').attributes('placeholder')).toBe('Buscar por usuário');
    });

    it('emits the search only after the typing pauses', async () => {
        const wrapper = mount(ListToolbar, { props: { search: '', searchLabel: 'Buscar' } });

        await wrapper.get('[data-test="search"]').setValue('ab');
        await wrapper.get('[data-test="search"]').setValue('abc');
        vi.advanceTimersByTime(300);
        expect(wrapper.emitted('update:search')).toBeUndefined();

        vi.advanceTimersByTime(100);
        expect(wrapper.emitted('update:search')).toEqual([['abc']]);
    });

    it('emits at once on Enter and does not repeat the same search', async () => {
        const wrapper = mount(ListToolbar, { props: { search: '', searchLabel: 'Buscar' } });

        await wrapper.get('[data-test="search"]').setValue('abc');
        await wrapper.get('[data-test="search"]').trigger('keydown.enter');
        await wrapper.setProps({ search: 'abc' });
        vi.advanceTimersByTime(1000);

        expect(wrapper.emitted('update:search')).toEqual([['abc']]);
    });

    it('follows a search cleared from outside', async () => {
        const wrapper = mount(ListToolbar, { props: { search: 'abc', searchLabel: 'Buscar' } });

        await wrapper.setProps({ search: '' });

        expect((wrapper.get('[data-test="search"]').element as HTMLInputElement).value).toBe('');
    });

    it('renders extra actions next to the search', () => {
        const wrapper = mount(ListToolbar, { props: { search: '', searchLabel: 'Buscar' }, slots: { default: '<button data-test="extra">Exportar CSV</button>' } });

        expect(wrapper.get('[data-test="extra"]').text()).toBe('Exportar CSV');
    });
});
