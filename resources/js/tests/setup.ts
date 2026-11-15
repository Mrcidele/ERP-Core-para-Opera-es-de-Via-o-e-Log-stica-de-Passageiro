import { afterEach } from 'vitest';
import { resetHttp } from '@/core/api/http';
import { clearToasts } from '@/core/ui/useToast';

// A sessão fica no localStorage e nos ganchos do cliente HTTP, e os avisos numa fila global: zera entre os testes.
afterEach(() => {
    localStorage.clear();
    resetHttp();
    clearToasts();
});
