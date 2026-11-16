import { http } from './http';

/** Nome do arquivo informado pela API no cabeçalho Content-Disposition. */
function filenameFrom(disposition: unknown): string | null {
    if (typeof disposition !== 'string') {
        return null;
    }

    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);

    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

/**
 * Baixa um arquivo de um endpoint protegido. O token vai no cabeçalho (um link direto não o enviaria),
 * então o conteúdo chega como blob e é entregue ao navegador por um link temporário.
 */
export async function downloadFile(url: string, params: Record<string, string>, fallbackName: string): Promise<string> {
    const response = await http.get<Blob>(url, { params, responseType: 'blob' });
    const name = filenameFrom(response.headers['content-disposition']) ?? fallbackName;
    const href = URL.createObjectURL(response.data);
    const link = document.createElement('a');

    link.href = href;
    link.download = name;
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(href);

    return name;
}
