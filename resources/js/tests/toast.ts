import { useToast, type Toast } from '@/core/ui/useToast';

/** Avisos na tela agora, do mais antigo para o mais novo. */
export const currentToasts = (): readonly Toast[] => useToast().toasts.value;

/** Texto do aviso mais recente ('' se não há nenhum). */
export const lastToast = (): string => currentToasts().at(-1)?.message ?? '';
