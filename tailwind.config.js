/** @type {import('tailwindcss').Config} */
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Tokens adaptados do Horizon UI Free (layout/dashboard),
 * com brand Levita em amarelo (#ffc107) e CTA azul.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                lightPrimary: '#F4F7FE',
                brand: {
                    DEFAULT: '#ffc107',
                    50: '#FFF9E6',
                    100: '#FFF0BF',
                    200: '#FFE380',
                    300: '#FFD54F',
                    400: '#FFCA28',
                    500: '#ffc107',
                    600: '#e0a800',
                    700: '#C79100',
                    800: '#A67900',
                    900: '#7A5800',
                    dark: '#e0a800',
                    light: '#ffcd38',
                },
                cta: {
                    DEFAULT: '#2563eb',
                    dark: '#1d4ed8',
                    500: '#2563eb',
                    600: '#1d4ed8',
                    700: '#1e40af',
                },
                navy: {
                    50: '#d0dcfb',
                    100: '#aac0fe',
                    200: '#a3b9f8',
                    300: '#728fea',
                    400: '#3652ba',
                    500: '#1b3bbb',
                    600: '#24388a',
                    700: '#1B254B',
                    800: '#111c44',
                    900: '#0b1437',
                },
                horizon: {
                    50: '#F5F6FA',
                    100: '#EEF0F6',
                    200: '#DADEEC',
                    300: '#C9D0E3',
                    400: '#B0BBD5',
                    500: '#A3AED0',
                    600: '#707eae',
                    700: '#2D396B',
                    800: '#1B2559',
                    900: '#0b1437',
                },
            },
            fontFamily: {
                sans: ['DM Sans', 'Figtree', ...defaultTheme.fontFamily.sans],
                dm: ['DM Sans', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                '3xl': '14px 17px 40px 4px',
                soft: '0px 18px 40px rgba(112, 144, 176, 0.12)',
            },
            borderRadius: {
                '2.5xl': '20px',
            },
        },
    },

    plugins: [forms],
};
