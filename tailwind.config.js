import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Azul de marca D'Salud (la cruz / la "D" del logo). Color primario.
                brand: {
                    50: '#eff8ff',
                    100: '#dcefff',
                    200: '#b2e1ff',
                    300: '#6ccbff',
                    400: '#22b1ff',
                    500: '#0098ee',
                    600: '#0078cc',
                    700: '#005fa5',
                    800: '#065088',
                    900: '#0b4470',
                    950: '#072a4a',
                },
                // Verde salud (la hoja / "Salud" del logo). Color de acento.
                salud: {
                    50: '#f4faeb',
                    100: '#e6f3d2',
                    200: '#cde8aa',
                    300: '#abd877',
                    400: '#8cc63f',
                    500: '#6ba82a',
                    600: '#52871d',
                    700: '#3f681a',
                    800: '#34531a',
                    900: '#2d471a',
                    950: '#15260a',
                },
            },
        },
    },

    plugins: [forms],
};
