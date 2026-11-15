import { afterEach } from 'vitest';
import { resetHttp } from '@/core/api/http';

// A sessão fica no localStorage e nos ganchos do cliente HTTP: zera entre os testes.
afterEach(() => {
    localStorage.clear();
    resetHttp();
});
