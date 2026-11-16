import { computed, ref, type Ref } from 'vue';
import { http } from './http';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

/** Metadados de página que toda listagem da API devolve em `meta`. */
export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface Paginated<T> {
    data: T[];
    meta: PageMeta;
}

export const DEFAULT_PER_PAGE = 15;
/** Maior página que a API aceita (PageQuery::MAX_PER_PAGE). */
export const MAX_PER_PAGE = 100;

export interface ListFilters {
    search?: string;
    status?: string | null;
    /** Filtros próprios de uma listagem (ex.: `needs_maintenance` na Frota); substituem os anteriores. */
    extra?: Record<string, string>;
}

const cleanParams = (search: string, status: string | null, extra: Record<string, string>): Record<string, string> => ({
    ...(search.trim() === '' ? {} : { search: search.trim() }),
    ...(status === null ? {} : { status }),
    ...extra,
});

/**
 * Estado de uma listagem paginada no servidor: a página atual, os filtros e o andamento da requisição.
 * A busca e o status viajam na query string; trocar um filtro volta para a primeira página.
 */
export function usePagedList<T>(url: string, perPage: number = DEFAULT_PER_PAGE) {
    const items = ref([]) as Ref<T[]>;
    const meta = ref<PageMeta>({ current_page: 1, last_page: 1, per_page: perPage, total: 0 });
    const search = ref('');
    const status = ref<string | null>(null);
    const extra = ref<Record<string, string>>({});
    const listStatus = ref<RequestStatus>('idle');
    /** Recarregando uma lista que já está na tela (troca de página, filtro, gravação). */
    const refreshing = ref(false);

    const filtering = computed(() => search.value.trim() !== '' || status.value !== null || Object.keys(extra.value).length > 0);

    // Respostas fora de ordem: só vale a da requisição mais recente.
    let latest = 0;

    async function fetchPage(page: number = meta.value.current_page): Promise<void> {
        const ticket = ++latest;

        if (listStatus.value === 'success') {
            refreshing.value = true;
        } else {
            listStatus.value = 'loading';
        }

        try {
            const response = await http.get<Paginated<T>>(url, {
                params: { page, per_page: perPage, ...cleanParams(search.value, status.value, extra.value) },
            });

            if (ticket !== latest) {
                return;
            }

            // A página pedida deixou de existir (ex.: o último registro dela foi excluído): volta para a última.
            if (response.data.data.length === 0 && response.data.meta.total > 0 && page > response.data.meta.last_page) {
                return await fetchPage(response.data.meta.last_page);
            }

            items.value = response.data.data;
            meta.value = response.data.meta;
            listStatus.value = 'success';
        } catch {
            if (ticket === latest) {
                listStatus.value = 'error';
            }
        } finally {
            if (ticket === latest) {
                refreshing.value = false;
            }
        }
    }

    function applyFilters(filters: ListFilters): Promise<void> {
        if (filters.search !== undefined) {
            search.value = filters.search;
        }

        if (filters.status !== undefined) {
            status.value = filters.status;
        }

        if (filters.extra !== undefined) {
            extra.value = filters.extra;
        }

        return fetchPage(1);
    }

    /** Filtros atuais no formato da query string (usado também pelas exportações). */
    const filterParams = (): Record<string, string> => cleanParams(search.value, status.value, extra.value);

    return { items, meta, search, status, extra, listStatus, refreshing, filtering, fetchPage, applyFilters, filterParams };
}

/**
 * Busca todos os registros de uma listagem paginada, página por página. Para as opções de formulário
 * (veículos, motoristas), que precisam da relação completa e não de uma página.
 */
export async function fetchEveryPage<T>(url: string, params: Record<string, string> = {}): Promise<T[]> {
    const all: T[] = [];
    let page = 1;
    let lastPage = 1;

    do {
        const response = await http.get<Paginated<T>>(url, { params: { ...params, page, per_page: MAX_PER_PAGE } });

        all.push(...response.data.data);
        lastPage = response.data.meta.last_page;
        page += 1;
    } while (page <= lastPage);

    return all;
}
