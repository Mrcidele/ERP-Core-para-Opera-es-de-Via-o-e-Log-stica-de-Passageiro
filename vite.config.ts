/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        // Os testes não usam o plugin do Laravel, que se recusa a subir o servidor de desenvolvimento em CI.
        ...(process.env.VITEST ? [] : [laravel({ input: ['resources/css/app.css', 'resources/js/app.ts'], refresh: true })]),
        vue(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.spec.ts'],
        setupFiles: ['resources/js/tests/setup.ts'],
    },
});
