@extends('layouts.app')
@section('title', 'Statistik Tren — hoaxlin.id')
@section('content')
<div style="padding:9rem 0 5rem; min-height:100vh;">
    <div class="container-app" style="max-width:960px;">
        <h1 style="font-size:2rem; font-weight:800; margin-bottom:0.5rem;">Statistik <span class="gradient-text">Tren</span></h1>
        <p style="color:var(--color-text-muted); margin-bottom:2rem;">Tren jumlah hoax per bulan dan distribusi label (E3).</p>

        <div class="glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1rem; font-weight:600; margin-bottom:1rem;">Tren 12 Bulan</h2>
            <canvas id="trendChart" height="120"></canvas>
        </div>

        <div class="glass-card" style="padding:1.5rem;">
            <h2 style="font-size:1rem; font-weight:600; margin-bottom:1rem;">Distribusi Label</h2>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                @foreach(['valid' => '#34d399', 'hoax' => '#f87171', 'meragukan' => '#fbbf24'] as $label => $color)
                    <div style="flex:1; min-width:120px; text-align:center; padding:1rem; background:rgba(99,102,241,0.08); border-radius:0.75rem;">
                        <div style="font-size:1.5rem; font-weight:800; color:{{ $color }};">{{ $byTopic[$label] ?? 0 }}</div>
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
const trend = @json($trend);
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trend.map(t => t.label),
        datasets: [
            { label: 'Total', data: trend.map(t => t.total), borderColor: '#818cf8', backgroundColor: 'rgba(129,140,248,0.15)', tension: 0.3 },
            { label: 'Hoax', data: trend.map(t => t.hoax), borderColor: '#f87171', backgroundColor: 'rgba(248,113,113,0.1)', tension: 0.3 },
        ]
    },
    options: { responsive: true, plugins: { legend: { labels: { color: '#94a3b8' } } }, scales: { x: { ticks: { color: '#64748b' } }, y: { ticks: { color: '#64748b' } } } }
});
</script>
@endpush
@endsection
