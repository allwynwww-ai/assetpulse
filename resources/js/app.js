import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

// Light, quiet defaults that match the Tailwind theme
Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#64748b';
Chart.defaults.borderColor = '#eef2f6';
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.legend.labels.boxHeight = 10;
Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.maintainAspectRatio = false;

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

/**
 * Layout sidebar: off-canvas drawer below lg (sidebarOpen), collapsible icon
 * rail on lg+ (html.sb-collapsed, persisted in localStorage; the inline head
 * script applies it before paint).
 */
const SIDEBAR_KEY = 'ap.sidebar';
window.sidebarState = () => ({
    sidebarOpen: false,
    collapsed: document.documentElement.classList.contains('sb-collapsed'),
    toggleSidebar() {
        this.collapsed = !this.collapsed;
        document.documentElement.classList.toggle('sb-collapsed', this.collapsed);
        try { localStorage.setItem(SIDEBAR_KEY, this.collapsed ? 'collapsed' : 'expanded'); } catch (e) { /* private mode */ }
        // Let Chart.js (and anything else listening) re-measure once the width transition has finished
        setTimeout(() => window.dispatchEvent(new Event('resize')), 230);
    },
});

Alpine.start();
