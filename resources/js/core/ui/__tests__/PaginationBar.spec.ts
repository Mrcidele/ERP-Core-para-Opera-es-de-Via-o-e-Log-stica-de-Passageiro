import { mount, type VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { PageMeta } from '@/core/api/pagination';
import PaginationBar from '../PaginationBar.vue';

const meta = (current: number, last: number, total: number, perPage = 15): PageMeta => ({ current_page: current, last_page: last, per_page: perPage, total });

const bar = (pageMeta: PageMeta, busy = false): VueWrapper =>
    mount(PaginationBar, { props: { meta: pageMeta, singular: 'veículo', plural: 'veículos', busy } });

/** Botões de página na ordem em que aparecem; '…' marca um trecho omitido. */
const pages = (wrapper: VueWrapper): string[] =>
    wrapper
        .findAll('nav > *')
        .slice(1, -1)
        .map((item) => item.text());

describe('PaginationBar', () => {
    it.each([
        [meta(1, 4, 48), '1 a 15 de 48 veículos'],
        [meta(4, 4, 48), '46 a 48 de 48 veículos'],
        [meta(1, 1, 3), '3 veículos'],
        [meta(1, 1, 1), '1 veículo'],
        [meta(1, 1, 0), '0 veículos'],
        [meta(2, 100, 1500), '16 a 30 de 1.500 veículos'],
    ])('summarizes what is on screen', (pageMeta, summary) => {
        expect(bar(pageMeta).get('[data-test="pagination-summary"]').text()).toBe(summary);
    });

    it('hides the page buttons when there is a single page', () => {
        expect(bar(meta(1, 1, 3)).find('nav').exists()).toBe(false);
    });

    it.each([
        [meta(1, 3, 45), ['1', '2', '3']],
        [meta(1, 10, 150), ['1', '2', '…', '10']],
        [meta(5, 10, 150), ['1', '…', '4', '5', '6', '…', '10']],
        [meta(3, 10, 150), ['1', '2', '3', '4', '…', '10']],
        [meta(10, 10, 150), ['1', '…', '9', '10']],
    ])('shows the first, the last and the neighbors of the current page', (pageMeta, expected) => {
        expect(pages(bar(pageMeta))).toEqual(expected);
    });

    it('marks the current page', () => {
        const wrapper = bar(meta(2, 3, 45));

        expect(wrapper.get('[data-test="page-2"]').attributes('aria-current')).toBe('page');
        expect(wrapper.get('[data-test="page-1"]').attributes('aria-current')).toBeUndefined();
    });

    it('emits the page to load from the numbers and from previous/next', async () => {
        const wrapper = bar(meta(2, 4, 60));

        await wrapper.get('[data-test="page-4"]').trigger('click');
        await wrapper.get('[data-test="page-previous"]').trigger('click');
        await wrapper.get('[data-test="page-next"]').trigger('click');
        await wrapper.get('[data-test="page-2"]').trigger('click');

        expect(wrapper.emitted('change')).toEqual([[4], [1], [3]]);
    });

    it('disables previous on the first page and next on the last', () => {
        expect(bar(meta(1, 3, 45)).get('[data-test="page-previous"]').attributes('disabled')).toBeDefined();
        expect(bar(meta(1, 3, 45)).get('[data-test="page-next"]').attributes('disabled')).toBeUndefined();
        expect(bar(meta(3, 3, 45)).get('[data-test="page-next"]').attributes('disabled')).toBeDefined();
    });

    it('locks the buttons while a page loads', async () => {
        const wrapper = bar(meta(2, 3, 45), true);

        await wrapper.get('[data-test="page-next"]').trigger('click');
        await wrapper.get('[data-test="page-3"]').trigger('click');

        expect(wrapper.emitted('change')).toBeUndefined();
        expect(wrapper.get('[data-test="page-3"]').attributes('disabled')).toBeDefined();
    });
});
