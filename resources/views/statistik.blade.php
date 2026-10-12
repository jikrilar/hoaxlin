@extends('layouts.app')
@section('title', 'Statistik Tren — hoaxlin.id')
@section('content')
<div style="padding:9rem 0 5rem; min-height:100vh;">
    <div class="container-app" style="max-width:960px;">
        <h1 style="font-size:2rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.5rem;">Statistik <span class="gradient-text">Tren</span></h1>
        <p style="color:var(--color-text-muted); margin-bottom:2rem;">Tren jumlah hoax per bulan dan distribusi label (E3).</p>

        <div class="glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1rem; font-weight:600; margin-bottom:1rem;">Tren 12 Bulan</h2>
            <canvas id="trendChart" height="120"></canvas>
        </div>

        <div class="glass-card" style="padding:1.5rem;">
            <h2 style="font-size:1rem; font-weight:600; margin-bottom:1rem;">Distribusi Label</h2>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                @foreach(['valid' => 'var(--ds-prediction-valid-fg)', 'hoax' => 'var(--ds-danger-fg)', 'meragukan' => 'var(--ds-warning-fg)'] as $label => $color)
                    <div style="flex:1; min-width:120px; text-align:center; padding:1rem; background:var(--ds-bg-selected); border-radius:0.75rem;">
                        <div style="font-size:1.5rem; font-weight:var(--ds-font-weight-semibold); color:{{ $color }};">{{ $byTopic[$label] ?? 0 }}</div>
                        <div style="font-size:0.75rem; color:var(--color-text-muted); text-transform:uppercase;">{{ ucfirst($label) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const token = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const trend = @json($trend);
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trend.map(t => t.label),
        datasets: [
            { label: 'Total', data: trend.map(t => t.total), borderColor: token('--ds-action-primary'), backgroundColor: token('--ds-bg-selected'), tension: 0.3 },
            { label: 'Hoax', data: trend.map(t => t.hoax), borderColor: token('--ds-danger-fg'), backgroundColor: token('--ds-danger-bg'), tension: 0.3 },
        ]
    },
    options: { responsive: true, plugins: { legend: { labels: { color: token('--ds-text-body') } } }, scales: { x: { ticks: { color: token('--ds-text-muted') } }, y: { ticks: { color: token('--ds-text-muted') } } } }
});
</script>
@endpush
@endsection
