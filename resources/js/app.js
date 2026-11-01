import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

// Self-hosted fonts (no runtime CDN)
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/plus-jakarta-sans/500.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';
import '@fontsource/plus-jakarta-sans/800.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';

window.Alpine = Alpine;
window.Chart = Chart;

/* ------------------------------------------------------------------ *
 * Theme tokens -> Chart.js. Colours are read from the CSS variables so
 * charts follow light / dark mode; pages re-render on 'ap:theme'.
 * ------------------------------------------------------------------ */
const cssVar = (n) => {
    const v = getComputedStyle(document.documentElement).getPropertyValue(n).trim();
    return v ? 'rgb(' + v.split(/\s+/).join(',') + ')' : '#888';
};
const cssVarA = (n, a) => {
    const v = getComputedStyle(document.documentElement).getPropertyValue(n).trim();
    return v ? 'rgba(' + v.split(/\s+/).join(',') + ',' + a + ')' : 'rgba(128,128,128,' + a + ')';
};
window.apTheme = {
    css: cssVar,
    cssA: cssVarA,
    isDark: () => document.documentElement.classList.contains('dark'),
    apply() {
        Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";
        Chart.defaults.font.size = 11.5;
        Chart.defaults.color = cssVar('--ink3');
        Chart.defaults.borderColor = cssVar('--line');
        Chart.defaults.plugins.legend.labels.boxWidth = 10;
        Chart.defaults.plugins.legend.labels.boxHeight = 10;
        Chart.defaults.plugins.tooltip.backgroundColor = '#15123A';
        Chart.defaults.plugins.tooltip.titleColor = '#fff';
        Chart.defaults.plugins.tooltip.bodyColor = '#E0E2FF';
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.cornerRadius = 10;
        Chart.defaults.maintainAspectRatio = false;
    },
};
window.apTheme.apply();

/**
 * Register a chart factory so it is rebuilt when the theme changes.
 * Usage: apChart(() => new Chart(el, {...}))
 */
const chartFactories = [];
window.apChart = (factory) => {
    const entry = { factory, chart: factory() };
    chartFactories.push(entry);
    return entry.chart;
};
window.addEventListener('ap:theme', () => {
    window.apTheme.apply();
    chartFactories.forEach((e) => { try { e.chart?.destroy(); } catch (_) { /* noop */ } e.chart = e.factory(); });
});

/** ₹ in Indian grouping, compact (L / Cr) for axes. */
window.inr = (v, compact = false) => {
    const n = Number(v || 0);
    if (compact) {
        const a = Math.abs(n);
        if (a >= 1e7) return '₹' + (n / 1e7).toFixed(2).replace(/\.?0+$/, '') + ' Cr';
        if (a >= 1e5) return '₹' + (n / 1e5).toFixed(2).replace(/\.?0+$/, '') + ' L';
        if (a >= 1e3) return '₹' + (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
    }
    return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 0 });
};

/* ------------------------------------------------------------------ *
 * Dark mode (html.dark, persisted; the inline head script applies it
 * before first paint).
 * ------------------------------------------------------------------ */
const THEME_KEY = 'ap.theme';
window.toggleTheme = () => {
    const dark = !document.documentElement.classList.contains('dark');
    document.documentElement.classList.toggle('dark', dark);
    try { localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light'); } catch (e) { /* private mode */ }
    window.dispatchEvent(new CustomEvent('ap:theme', { detail: { dark } }));
};

/**
 * Layout shell: off-canvas drawer below lg (sidebarOpen), collapsible icon
 * rail on lg+ (html.sb-collapsed, persisted in localStorage; the inline head
 * script applies it before paint), theme flag for the toggle icon.
 */
const SIDEBAR_KEY = 'ap.sidebar';
window.sidebarState = () => ({
    sidebarOpen: false,
    collapsed: document.documentElement.classList.contains('sb-collapsed'),
    dark: document.documentElement.classList.contains('dark'),
    init() {
        window.addEventListener('ap:theme', (e) => { this.dark = e.detail.dark; });
    },
    toggleSidebar() {
        this.collapsed = !this.collapsed;
        document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
        try { localStorage.setItem(SIDEBAR_KEY, this.collapsed ? 'collapsed' : 'expanded'); } catch (e) { /* private mode */ }
        // Let Chart.js (and anything else listening) re-measure once the width transition has finished
        setTimeout(() => window.dispatchEvent(new Event('resize')), 230);
    },
    toggleTheme() { window.toggleTheme(); },
});

/* ------------------------------------------------------------------ *
 * Command palette (Ctrl/Cmd+K). Pages + actions come from the server
 * (role-filtered) in #ap-palette-data; assets from the tenant-scoped
 * JSON endpoint.
 * ------------------------------------------------------------------ */
const isTyping = (e) => {
    const t = e.target;
    return t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName));
};
const norm = (s) => (s || '').toString().toLowerCase();

window.commandPalette = () => ({
    open: false,
    q: '',
    filter: 'all',
    assets: [],
    loading: false,
    active: 0,
    pages: [],
    actions: [],
    searchUrl: null,
    newUrl: null,
    _t: null,
    _seq: 0,
    init() {
        try {
            const d = JSON.parse(document.getElementById('ap-palette-data')?.textContent || '{}');
            this.pages = d.pages || [];
            this.actions = d.actions || [];
            this.searchUrl = d.searchUrl || null;
            this.newUrl = d.newUrl || null;
        } catch (e) { /* no data */ }
        window.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.open ? this.close() : this.show(); return; }
            if (!this.open && !isTyping(e) && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if (e.key === '/') { e.preventDefault(); this.show(); }
                else if (e.key.toLowerCase() === 'n' && this.newUrl) { e.preventDefault(); window.location = this.newUrl; }
            }
        });
        window.addEventListener('ap:palette', (e) => this.show(e.detail?.filter || 'all'));
        this.$watch('q', () => { this.active = 0; this.fetchAssets(); });
        this.$watch('filter', () => { this.active = 0; });
    },
    show(filter = 'all') {
        this.filter = filter;
        this.open = true;
        document.documentElement.classList.add('overflow-hidden');
        this.$nextTick(() => this.$refs.input?.focus());
    },
    close() {
        this.open = false;
        document.documentElement.classList.remove('overflow-hidden');
    },
    fetchAssets() {
        clearTimeout(this._t);
        const q = this.q.trim();
        if (!this.searchUrl || q.length < 1) { this.assets = []; this.loading = false; return; }
        this.loading = true;
        const seq = ++this._seq;
        this._t = setTimeout(async () => {
            try {
                const r = await fetch(this.searchUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const j = r.ok ? await r.json() : { data: [] };
                if (seq === this._seq) this.assets = j.data || [];
            } catch (e) { if (seq === this._seq) this.assets = []; }
            if (seq === this._seq) this.loading = false;
        }, 140);
    },
    match(item) {
        const q = norm(this.q).trim();
        if (!q) return true;
        const hay = norm(item.label + ' ' + (item.group || '') + ' ' + (item.keywords || ''));
        return q.split(/\s+/).every((w) => hay.includes(w));
    },
    get sections() {
        const out = [];
        const q = this.q.trim();
        if ((this.filter === 'all' || this.filter === 'assets') && this.assets.length) {
            out.push({ key: 'assets', title: 'Assets', items: this.assets.map((a) => ({ type: 'asset', ...a, label: a.name, sub: [a.code, a.location, a.nbv ? 'NBV ' + a.nbv : null].filter(Boolean).join(' · ') })) });
        }
        if (this.filter === 'all' || this.filter === 'pages') {
            const p = this.pages.filter((i) => this.match(i));
            if (p.length) out.push({ key: 'pages', title: 'Pages & reports', items: (q || this.filter === 'pages' ? p : p.slice(0, 6)).map((i) => ({ type: 'page', ...i })) });
        }
        if (this.filter === 'all' || this.filter === 'actions') {
            const a = this.actions.filter((i) => this.match(i));
            if (a.length) out.push({ key: 'actions', title: 'Quick actions', items: a.map((i) => ({ type: 'action', ...i })) });
        }
        let n = 0;
        out.forEach((s) => s.items.forEach((i) => { i._i = n++; }));
        return out;
    },
    get flat() { return this.sections.flatMap((s) => s.items); },
    get counts() {
        return { assets: this.assets.length, pages: this.pages.filter((i) => this.match(i)).length, actions: this.actions.filter((i) => this.match(i)).length };
    },
    move(d) {
        const n = this.flat.length;
        if (!n) return;
        this.active = (this.active + d + n) % n;
        this.$nextTick(() => this.$refs.list?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
    },
    cycleFilter(e) {
        const order = ['all', 'assets', 'pages', 'actions'];
        const i = order.indexOf(this.filter);
        this.filter = order[(i + (e.shiftKey ? order.length - 1 : 1)) % order.length];
    },
    go(item) {
        if (!item) return;
        if (item.js === 'theme') { window.toggleTheme(); this.close(); return; }
        if (item.js === 'sidebar') { document.querySelector('#sidebar-toggle')?.click(); this.close(); return; }
        if (item.js === 'scan') { this.close(); window.dispatchEvent(new Event('ap:scan')); return; }
        if (item.url) window.location = item.url;
    },
    enter() { this.go(this.flat[this.active]); },
    highlight(text) {
        const esc = (s) => (s || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const q = this.q.trim();
        let t = esc(text);
        if (!q) return t;
        q.split(/\s+/).filter((w) => w.length > 1).forEach((w) => {
            const re = new RegExp('(' + esc(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
            t = t.replace(re, '<mark class="bg-transparent font-semibold text-brand-600 dark:text-brand-300">$1</mark>');
        });
        return t;
    },
});

/* ------------------------------------------------------------------ *
 * QR scanner (mobile tab bar). Uses the browser's native BarcodeDetector
 * when available; otherwise falls back to the palette's asset lookup.
 * Asset labels encode the asset's URL on this site.
 * ------------------------------------------------------------------ */
window.qrScanner = () => ({
    open: false,
    error: null,
    stream: null,
    _raf: null,
    init() {
        window.addEventListener('ap:scan', () => this.start());
    },
    async start() {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            window.dispatchEvent(new CustomEvent('ap:palette', { detail: { filter: 'assets' } }));
            return;
        }
        this.error = null;
        this.open = true;
        try {
            const det = new window.BarcodeDetector({ formats: ['qr_code'] });
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            const v = this.$refs.video;
            v.srcObject = this.stream;
            await v.play();
            const tick = async () => {
                if (!this.open) return;
                try {
                    const codes = await det.detect(v);
                    const hit = codes.find((c) => c.rawValue);
                    if (hit) { this.handle(hit.rawValue); return; }
                } catch (e) { /* frame not ready */ }
                this._raf = requestAnimationFrame(tick);
            };
            this._tick = tick;
            tick();
        } catch (e) {
            this.error = 'Camera not available. Allow camera access, or search for the asset instead.';
        }
    },
    handle(raw) {
        try {
            const u = new URL(raw, window.location.origin);
            if (u.origin === window.location.origin) { this.stop(); window.location = u.href; return; }
        } catch (e) { /* not a URL */ }
        this.error = 'That QR code is not an AssetPulse asset label.';
        setTimeout(() => this._tick?.(), 1500);
    },
    search() {
        this.stop();
        window.dispatchEvent(new CustomEvent('ap:palette', { detail: { filter: 'assets' } }));
    },
    stop() {
        this.open = false;
        cancelAnimationFrame(this._raf);
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null;
    },
});

/* ------------------------------------------------------------------ *
 * Asset register bulk selection.
 * ------------------------------------------------------------------ */
window.bulkSelect = (pageIds = [], opts = {}) => ({
    ids: [],
    pageIds,
    filters: !!opts.filters,
    blocked: opts.blocked || [],
    nbv: opts.nbv || {},
    get hasBlocked() { return this.ids.some((id) => this.blocked.includes(id)); },
    get selNbv() { return this.ids.reduce((s, id) => s + (Number(this.nbv[id]) || 0), 0); },
    get count() { return this.ids.length; },
    get allOnPage() { return this.pageIds.length > 0 && this.pageIds.every((id) => this.ids.includes(id)); },
    toggleAll() {
        if (this.allOnPage) this.ids = this.ids.filter((id) => !this.pageIds.includes(id));
        else this.ids = [...new Set([...this.ids, ...this.pageIds])];
    },
    clear() { this.ids = []; },
    url(base, key = 'ids[]') {
        const u = new URL(base, window.location.origin);
        this.ids.forEach((id) => u.searchParams.append(key, id));
        return u.toString();
    },
    exportUrl(base) {
        const u = new URL(base, window.location.origin);
        u.searchParams.set('ids', this.ids.join(','));
        return u.toString();
    },
});

Alpine.start();
