import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

/**
 * AssetPulse design tokens.
 *  - brand: indigo (#4840D6 / #3B33B4 / #221F5F)
 *  - canvas / card / line / soft / ink / ink2 / ink3: semantic surfaces & text, driven by CSS
 *    variables so light and dark themes share the same class names.
 *  - slate: also variable-driven, so every legacy page that uses slate-* greys picks up the dark
 *    theme automatically (the scale is inverted under html.dark).
 */
const v = (name) => `rgb(var(--${name}) / <alpha-value>)`;
const slate = Object.fromEntries([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((k) => [k, v(`slate-${k}`)]));

// Tinted colours that legacy views use as "pale background + dark text" pairs. Under .dark we turn
// them into translucent tints with light text so badges, alerts and pills stay readable.
const TINTS = ['emerald', 'amber', 'sky', 'rose', 'violet', 'blue', 'indigo', 'teal', 'orange', 'brand', 'mint', 'yellow', 'red', 'green', 'lime', 'fuchsia', 'cyan', 'purple'];

export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],
    safelist: [
        { pattern: /^(bg|text|ring|border)-(emerald|amber|sky|rose|slate|violet|blue|indigo|teal|orange|brand|mint)-(50|100|200|300|400|500|600|700)$/ },
        // status pills: bg-x-500/10 text-x-700 ring-x-600/20 dark:text-x-300, dot bg-x-500
        { pattern: /^bg-(emerald|amber|sky|rose|slate|violet|blue|indigo|teal|orange|brand|mint)-500\/(10|15)$/ },
        { pattern: /^ring-(emerald|amber|sky|rose|slate|violet|blue|indigo|teal|orange|brand|mint)-600\/20$/ },
        { pattern: /^text-(emerald|amber|sky|rose|slate|violet|blue|indigo|teal|orange|brand|mint)-300$/, variants: ['dark'] },
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                slate,
                brand: {
                    50: '#EEF0FF', 100: '#E0E3FF', 200: '#C6CAFE', 300: '#A2A6FB', 400: '#7B7DF4',
                    500: '#5D5BE8', 600: '#4840D6', 700: '#3B33B4', 800: '#2F2A8E', 900: '#221F5F', 950: '#15123A',
                },
                mint: {
                    50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857',
                },
                canvas: v('bg'), card: v('card'), line: v('line'), soft: v('soft'),
                ink: v('ink'), ink2: v('ink2'), ink3: v('ink3'),
            },
            boxShadow: {
                card: '0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.06)',
                lift: '0 8px 24px -6px rgba(16,24,40,.12), 0 2px 6px rgba(16,24,40,.05)',
                pop: '0 24px 60px -12px rgba(15,18,60,.35)',
                soft: '0 4px 24px -6px rgb(15 23 42 / 0.08)',
            },
            borderRadius: { xl: '0.85rem' },
        },
    },
    plugins: [
        forms,
        plugin(({ addBase, theme }) => {
            const rules = {};
            const rgb = (hex, a) => {
                const h = hex.replace('#', '');
                const n = parseInt(h.length === 3 ? h.split('').map((c) => c + c).join('') : h, 16);
                return `rgb(${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255} / ${a})`;
            };
            for (const t of TINTS) {
                const c = t === 'brand' ? theme('colors.brand') : t === 'mint' ? theme('colors.mint') : colors[t];
                if (!c) continue;
                const base = c[500];
                const light = c[300] || c[400];
                for (const k of [50, 100]) {
                    rules[`.dark .bg-${t}-${k}`] = { 'background-color': rgb(base, k === 50 ? 0.12 : 0.18) };
                    rules[`.dark .hover\\:bg-${t}-${k}:hover`] = { 'background-color': rgb(base, 0.2) };
                }
                rules[`.dark .bg-${t}-50\\/40`] = { 'background-color': rgb(base, 0.08) };
                rules[`.dark .bg-${t}-50\\/60`] = { 'background-color': rgb(base, 0.1) };
                for (const k of [600, 700, 800, 900]) rules[`.dark .text-${t}-${k}`] = { color: light };
                for (const k of [100, 200, 300]) {
                    rules[`.dark .ring-${t}-${k}`] = { '--tw-ring-color': rgb(base, 0.3) };
                    rules[`.dark .border-${t}-${k}`] = { 'border-color': rgb(base, 0.3) };
                }
                rules[`.dark .from-${t}-50`] = { '--tw-gradient-from': `${rgb(base, 0.12)} var(--tw-gradient-from-position)`, '--tw-gradient-to': `${rgb(base, 0)} var(--tw-gradient-to-position)`, '--tw-gradient-stops': 'var(--tw-gradient-from), var(--tw-gradient-to)' };
                rules[`.dark .to-${t}-50`] = { '--tw-gradient-to': `${rgb(base, 0.08)} var(--tw-gradient-to-position)` };
            }
            addBase(rules);
        }),
    ],
};
