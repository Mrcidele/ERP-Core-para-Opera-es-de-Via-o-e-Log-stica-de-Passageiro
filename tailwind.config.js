import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.ts', './resources/**/*.vue'],
    theme: {
        extend: {
            colors: {
                // Azul-asfalto: texto, barra lateral e ação principal.
                ink: {
                    50: '#F2F5FA',
                    100: '#E3E9F3',
                    200: '#C5D0E3',
                    300: '#9AAAC6',
                    400: '#6B7FA3',
                    500: '#4A5E85',
                    600: '#35476B',
                    700: '#263556',
                    800: '#1A2742',
                    900: '#101B33',
                },
                // Âmbar de letreiro: só para o item ativo e o foco.
                signal: {
                    100: '#FFF3CC',
                    400: '#FFC83D',
                    500: '#F5B700',
                    700: '#8A6400',
                },
                canvas: '#F3F5F9',
                road: { 100: '#DDF3E6', 600: '#1F7A4D', 800: '#145233' },
                brake: { 100: '#FBE4E1', 600: '#B93A2B', 700: '#962C20' },
            },
            fontFamily: {
                sans: ['"Public Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Archivo', '"Public Sans"', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                panel: '0 1px 2px rgb(16 27 51 / 0.06), 0 8px 24px -12px rgb(16 27 51 / 0.18)',
                drawer: '-24px 0 48px -24px rgb(16 27 51 / 0.35)',
            },
        },
    },
    plugins: [],
};
