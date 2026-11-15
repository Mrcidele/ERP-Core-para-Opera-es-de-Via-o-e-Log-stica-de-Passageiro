import { readonly, ref } from 'vue';

export type ToastTone = 'success' | 'error' | 'warning';

export interface Toast {
    id: number;
    tone: ToastTone;
    /** Linha curta em destaque, ex.: "Acesso negado". */
    title: string | null;
    message: string;
}

const VISIBLE_FOR_MS: Record<ToastTone, number> = { success: 5000, warning: 8000, error: 8000 };

// Uma fila só para a aplicação inteira, exibida por ToastContainer.
const toasts = ref<Toast[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();
let sequence = 0;

function dismiss(id: number): void {
    const timer = timers.get(id);

    if (timer !== undefined) {
        clearTimeout(timer);
        timers.delete(id);
    }

    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function show(tone: ToastTone, message: string, title: string | null = null): void {
    // Várias respostas iguais ao mesmo tempo (ex.: três 403) viram um aviso só.
    if (toasts.value.some((toast) => toast.tone === tone && toast.message === message && toast.title === title)) {
        return;
    }

    const id = ++sequence;

    toasts.value = [...toasts.value, { id, tone, title, message }];
    timers.set(
        id,
        setTimeout(() => dismiss(id), VISIBLE_FOR_MS[tone]),
    );
}

/** Tira todos os avisos da tela (usado entre testes e ao sair da sessão). */
export function clearToasts(): void {
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
    toasts.value = [];
}

/**
 * Notificações globais: confirmações de sucesso e alertas de erro.
 *
 *     const toast = useToast();
 *     toast.success('Veículo ABC1D23 cadastrado.');
 *     toast.error('Você não tem permissão para executar esta ação.', 'Acesso negado');
 */
export function useToast() {
    return {
        toasts: readonly(toasts),
        success: (message: string, title: string | null = null): void => show('success', message, title),
        error: (message: string, title: string | null = null): void => show('error', message, title),
        warning: (message: string, title: string | null = null): void => show('warning', message, title),
        dismiss,
    };
}
