import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.ts', './resources/**/*.vue'],
    theme: {
        extend: {
            colors: {
                // Azul-asfalto (escala slate): texto, barra lateral e ação principal. 950 é o fundo do centro de comando.
                ink: {
                    50: '#F8FAFC',
                    100: '#F1F5F9',
                    200: '#E2E8F0',
                    300: '#CBD5E1',
                    400: '#94A3B8',
                    500: '#64748B',
                    600: '#475569',
                    700: '#334155',
                    800: '#1E293B',
                    900: '#0F172A',
                    950: '#0B1120',
                },
                // Âmbar de letreiro: item ativo, foco e alertas que pedem atenção.
                signal: {
                    50: '#FFFBEB',
                    100: '#FEF3C7',
                    400: '#FBBF24',
                    500: '#F59E0B',
                    600: '#D97706',
                    700: '#B45309',
                },
                canvas: '#F8FAFC',
                road: { 50: '#ECFDF5', 100: '#D1FAE5', 500: '#10B981', 600: '#059669', 800: '#065F46' },
                brake: { 50: '#FEF2F2', 100: '#FEE2E2', 500: '#EF4444', 600: '#DC2626', 700: '#B91C1C' },
                // Azul da faixa da placa Mercosul.
                plate: '#003DA5',
            },
            fontFamily: {
                sans: ['"Public Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Archivo', '"Public Sans"', ...defaultTheme.fontFamily.sans],
                // Placas, chassis, horários, quilometragens e valores.
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                card: '0 1px 2px rgb(15 23 42 / 0.04), 0 1px 3px rgb(15 23 42 / 0.06)',
                panel: '0 1px 2px rgb(15 23 42 / 0.04), 0 1px 3px rgb(15 23 42 / 0.06)',
                raised: '0 4px 6px -2px rgb(15 23 42 / 0.05), 0 12px 24px -6px rgb(15 23 42 / 0.12)',
                drawer: '-16px 0 40px -12px rgb(11 17 32 / 0.28)',
            },
            backdropBlur: {
                xs: '2px',
            },
        },
    },
    plugins: [],
};
