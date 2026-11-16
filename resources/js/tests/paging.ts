import type { PageMeta, Paginated } from '@/core/api/pagination';

/** Resposta de uma listagem paginada da API, como o Axios a entrega. */
export const page = <T>(items: T[], meta: Partial<PageMeta> = {}): { data: Paginated<T> } => ({
    data: { data: items, meta: { current_page: 1, last_page: 1, per_page: 15, total: items.length, ...meta } },
});

/** Parâmetros que a tela envia ao pedir uma página. */
export const pageParams = (params: Record<string, string | number> = {}): { params: Record<string, string | number> } => ({
    params: { page: 1, per_page: 15, ...params },
});
