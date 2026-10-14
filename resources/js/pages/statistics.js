import { defineModule } from '../core/scope.js';

// The native tables remain the source of readable data if this enhancement fails.
export function createStatisticsModule(loadChart = () => import('./statistics-chart.js')) {
    return defineModule((root, scope) => {
        const canvas = root.querySelector('[data-statistics-trend]');
        const panel = root.querySelector('[data-statistics-chart-panel]');
        if (!canvas || !panel) return;
        let trend;
        try {
            trend = JSON.parse(canvas.dataset.statisticsTrend);
            if (!Array.isArray(trend) || trend.length === 0 || trend.length > 12
                || !trend.every(month => typeof month.label === 'string' && month.label.length > 0 && month.label.length <= 120
                    && ['total', 'hoax', 'valid'].every(key => Number.isSafeInteger(month[key]) && month[key] >= 0)
                    && month.hoax + month.valid <= month.total)
                || !trend.some(month => month.total > 0)) return;
        } catch { return; }

        const view = root.ownerDocument.defaultView;
        const token = name => view.getComputedStyle(root.ownerDocument.documentElement).getPropertyValue(name).trim();
        const active = () => !scope.signal.aborted && canvas.isConnected;
        let chart;
        scope.own(() => { chart?.destroy(); chart = undefined; panel.hidden = true; });

        Promise.resolve().then(() => active() ? loadChart() : null).then(loaded => {
            if (!loaded || !active()) return;
            const Chart = loaded.default;
            try {
                panel.hidden = false; // Measure a visible canvas before Chart.js initialization.
                chart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: trend.map(month => month.label),
                        datasets: [
                            { label: 'Total submission', data: trend.map(month => month.total), borderColor: token('--ds-action-primary'), pointStyle: 'rect' },
                            { label: 'Dengan label Hoaks', data: trend.map(month => month.hoax), borderColor: token('--ds-danger-fg'), borderDash: [6, 3], pointStyle: 'triangle' },
                            { label: 'Dengan label Valid', data: trend.map(month => month.valid), borderColor: token('--ds-text-body'), borderDash: [2, 3], pointStyle: 'circle' },
                        ].map(series => ({ ...series, backgroundColor: series.borderColor, fill: false, tension: 0, borderWidth: 2, pointRadius: 3 })),
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, animation: false,
                        font: { family: token('--ds-font-ui'), size: 14 },
                        plugins: { legend: { position: 'bottom', onClick: () => {}, labels: { color: token('--ds-text-body'), usePointStyle: true, boxWidth: 14, boxHeight: 14 } } },
                        scales: {
                            x: { ticks: { color: token('--ds-text-muted'), maxRotation: 0, maxTicksLimit: 6 }, grid: { display: false } },
                            y: { beginAtZero: true, ticks: { color: token('--ds-text-muted'), precision: 0 }, grid: { color: token('--ds-border-subtle') } },
                        },
                    },
                });
                if (!active()) { chart.destroy(); chart = undefined; panel.hidden = true; }
            } catch {
                // Chart.js may register an instance before a constructor failure.
                Chart.getChart?.(canvas)?.destroy();
                panel.hidden = true;
            }
        }).catch(() => { if (active()) panel.hidden = true; });
    });
}

export const mount = createStatisticsModule();
