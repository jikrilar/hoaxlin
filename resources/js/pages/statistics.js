import { defineModule } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    const canvas = root.querySelector('[data-page="statistics"] [data-trend]');
    const view = root.ownerDocument.defaultView;
    if (!canvas) return;
    const trend = JSON.parse(canvas.dataset.trend);
    const token = name => view.getComputedStyle(root.ownerDocument.documentElement).getPropertyValue(name).trim();
    let chart;
    const render = () => {
        if (scope.signal.aborted || !canvas.isConnected || !view.Chart || chart) return;
        chart = new view.Chart(canvas, {
            type: 'line',
            data: {
                labels: trend.map(item => item.label),
                datasets: [
                    { label: 'Total', data: trend.map(item => item.total), borderColor: token('--ds-action-primary'), backgroundColor: token('--ds-bg-selected'), tension: 0.3 },
                    { label: 'Hoax', data: trend.map(item => item.hoax), borderColor: token('--ds-danger-fg'), backgroundColor: token('--ds-danger-bg'), tension: 0.3 },
                ],
            },
            options: { responsive: true, plugins: { legend: { labels: { color: token('--ds-text-body') } } }, scales: { x: { ticks: { color: token('--ds-text-muted') } }, y: { ticks: { color: token('--ds-text-muted') } } } },
        });
        scope.own(() => chart.destroy());
    };
    // Navigate can fire before a newly inserted external script finishes loading.
    // The listener belongs to this root and is removed if navigation wins the race.
    const script = root.querySelector('script[data-statistics-chart]');
    if (script) scope.listen(script, 'load', render, { once: true });
    render(); // Aggregate SSR remains readable when the CDN is unavailable.
});
