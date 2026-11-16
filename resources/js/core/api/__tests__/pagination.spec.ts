import { beforeEach, describe, expect, it, vi } from 'vitest';
import { page, pageParams } from '@/tests/paging';
import { downloadFile } from '../download';
import { fetchEveryPage, usePagedList } from '../pagination';

const http = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

describe('usePagedList', () => {
    beforeEach(() => {
        http.get.mockReset();
    });

    it('loads the first page with the default size and keeps the totals', async () => {
        http.get.mockResolvedValue(page(['a', 'b'], { total: 32, last_page: 3 }));
        const list = usePagedList<string>('/things');

        await list.fetchPage();

        expect(http.get).toHaveBeenCalledWith('/things', pageParams());
        expect(list.items.value).toEqual(['a', 'b']);
        expect(list.meta.value).toEqual({ current_page: 1, last_page: 3, per_page: 15, total: 32 });
        expect(list.listStatus.value).toBe('success');
        expect(list.filtering.value).toBe(false);
    });

    it('goes back to the first page when a filter changes and trims the search', async () => {
        http.get.mockResolvedValue(page(['a'], { current_page: 3, total: 32, last_page: 3 }));
        const list = usePagedList<string>('/things');
        await list.fetchPage(3);

        await list.applyFilters({ search: '  abc ', status: 'active' });

        expect(http.get).toHaveBeenLastCalledWith('/things', pageParams({ search: 'abc', status: 'active' }));
        expect(list.filtering.value).toBe(true);
        expect(list.filterParams()).toEqual({ search: 'abc', status: 'active' });

        await list.applyFilters({ search: '', status: null });

        expect(http.get).toHaveBeenLastCalledWith('/things', pageParams());
        expect(list.filterParams()).toEqual({});
    });

    it('reloads the current page by default', async () => {
        http.get.mockResolvedValue(page(['a'], { current_page: 2, total: 20, last_page: 2 }));
        const list = usePagedList<string>('/things');
        await list.fetchPage(2);

        await list.fetchPage();

        expect(http.get).toHaveBeenLastCalledWith('/things', pageParams({ page: 2 }));
    });

    it('falls back to the last page when the requested one no longer exists', async () => {
        http.get
            .mockResolvedValueOnce(page([], { current_page: 3, total: 30, last_page: 2 }))
            .mockResolvedValueOnce(page(['z'], { current_page: 2, total: 30, last_page: 2 }));
        const list = usePagedList<string>('/things');

        await list.fetchPage(3);

        expect(http.get).toHaveBeenLastCalledWith('/things', pageParams({ page: 2 }));
        expect(list.items.value).toEqual(['z']);
        expect(list.meta.value.current_page).toBe(2);
    });

    it('keeps the list on screen while it reloads and flags it as refreshing', async () => {
        http.get.mockResolvedValue(page(['a']));
        const list = usePagedList<string>('/things');
        await list.fetchPage();

        let release: (value: unknown) => void = () => undefined;
        http.get.mockReturnValue(new Promise((resolve) => (release = resolve)));
        const pending = list.fetchPage(2);

        expect(list.listStatus.value).toBe('success');
        expect(list.refreshing.value).toBe(true);
        expect(list.items.value).toEqual(['a']);

        release(page(['b'], { current_page: 2 }));
        await pending;

        expect(list.refreshing.value).toBe(false);
        expect(list.items.value).toEqual(['b']);
    });

    it('ignores a slow response that arrives after a newer one', async () => {
        let releaseSlow: (value: unknown) => void = () => undefined;
        http.get.mockReturnValueOnce(new Promise((resolve) => (releaseSlow = resolve))).mockResolvedValueOnce(page(['novo']));
        const list = usePagedList<string>('/things');

        const slow = list.applyFilters({ search: 'a' });
        await list.applyFilters({ search: 'ab' });
        releaseSlow(page(['velho']));
        await slow;

        expect(list.items.value).toEqual(['novo']);
        expect(list.listStatus.value).toBe('success');
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const list = usePagedList<string>('/things');

        await list.fetchPage();

        expect(list.listStatus.value).toBe('error');
        expect(list.refreshing.value).toBe(false);
    });
});

describe('fetchEveryPage', () => {
    beforeEach(() => {
        http.get.mockReset();
    });

    it('walks every page with the largest size the API accepts', async () => {
        http.get.mockImplementation((_url: string, config: { params: { page: number } }) =>
            Promise.resolve(page([`item-${config.params.page}`], { current_page: config.params.page, last_page: 3, per_page: 100, total: 3 })),
        );

        const items = await fetchEveryPage<string>('/things', { status: 'active' });

        expect(items).toEqual(['item-1', 'item-2', 'item-3']);
        expect(http.get).toHaveBeenCalledTimes(3);
        expect(http.get).toHaveBeenLastCalledWith('/things', { params: { status: 'active', page: 3, per_page: 100 } });
    });

    it('makes a single request when everything fits in one page', async () => {
        http.get.mockResolvedValue(page(['a']));

        expect(await fetchEveryPage<string>('/things')).toEqual(['a']);
        expect(http.get).toHaveBeenCalledTimes(1);
    });
});

describe('downloadFile', () => {
    beforeEach(() => {
        http.get.mockReset();
    });

    it.each([
        ['attachment; filename=manifesto-2026-11-15.csv', 'manifesto-2026-11-15.csv'],
        ['attachment; filename="ordens de servico.csv"', 'ordens de servico.csv'],
        ["attachment; filename*=UTF-8''ordens%20de%20servi%C3%A7o.csv", 'ordens de serviço.csv'],
        [undefined, 'padrao.csv'],
    ])('saves the blob under the name the API sent (%s)', async (disposition, expected) => {
        const createObjectURL = vi.fn(() => 'blob:arquivo');
        const revokeObjectURL = vi.fn();
        vi.stubGlobal('URL', Object.assign(URL, { createObjectURL, revokeObjectURL }));
        let downloaded = '';
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
            downloaded = this.download;
        });
        const blob = new Blob(['a;b']);
        http.get.mockResolvedValue({ data: blob, headers: { 'content-disposition': disposition } });

        const name = await downloadFile('/things/export', { status: 'open' }, 'padrao.csv');

        expect(http.get).toHaveBeenCalledWith('/things/export', { params: { status: 'open' }, responseType: 'blob' });
        expect(name).toBe(expected);
        expect(downloaded).toBe(expected);
        expect(createObjectURL).toHaveBeenCalledWith(blob);
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:arquivo');
        expect(document.querySelector('a[download]')).toBeNull();

        click.mockRestore();
    });
});
