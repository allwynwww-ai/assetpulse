import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],
    safelist: [
        { pattern: /^(bg|text|ring|border)-(emerald|amber|sky|rose|slate|violet|blue|indigo|teal|orange|brand|mint)-(50|100|200|300|500|600|700)$/ },
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa',
                    500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a',
                },
                mint: {
                    50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                soft: '0 4px 24px -6px rgb(15 23 42 / 0.08)',
            },
            borderRadius: { xl: '0.85rem' },
        },
    },
    plugins: [forms],
};
