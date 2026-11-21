@php
    $metrics = app(\App\Services\Admin\DashboardMetrics::class);
    $days = $this->days();
    $mode = $this->mode();
    $trend = $mode === 'trend' ? $metrics->trend($days) : null;
    $breakdown = $mode === 'trend' ? null : $metrics->breakdown($days);
    $available = $mode === 'trend' ? $trend['available'] : $breakdown['available'];
@endphp

@if(! $available)
    <p class="hl-admin-note" role="status">Data tabel belum tersedia. Muat ulang halaman untuk mencoba membaca data kembali.</p>
@elseif($mode === 'trend')
    <p class="hl-admin-note">{{ $trend['start'] }}–{{ $trend['end'] }} · {{ $trend['timezone'] }}. Grafik dan tabel memakai data yang sama.</p>
    <table class="hl-admin-data-table" data-admin-trend>
        <caption>Pemeriksaan menurut tanggal pengiriman · semua status</caption>
        <thead><tr><th scope="col">Tanggal</th><th scope="col">Semua</th><th scope="col">Valid</th><th scope="col">Hoaks</th><th scope="col">Belum terverifikasi</th></tr></thead>
        <tbody>
            @foreach($trend['rows'] as $row)
                <tr data-date="{{ $row['date'] }}"><th scope="row">{{ $row['label'] }}</th><td>{{ $row['total'] }}</td><td>{{ $row['valid'] }}</td><td>{{ $row['hoax'] }}</td><td>{{ $row['meragukan'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
@elseif($mode === 'labels')
    <table class="hl-admin-data-table" data-admin-breakdown="labels">
        <caption>Label hasil tersimpan dalam jendela</caption>
        <thead><tr><th scope="col">Label</th><th scope="col">Jumlah</th></tr></thead>
        <tbody>
            @forelse($breakdown['labels'] as $key => $count)
                <tr><th scope="row">{{ \App\View\Presenters\DetectionPresentation::label($key)['text'] }}</th><td>{{ $count }}</td></tr>
            @empty
                <tr><td colspan="2">Belum ada hasil berlabel dalam jendela ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($mode === 'inputs')
    <table class="hl-admin-data-table" data-admin-breakdown="inputs">
        <caption>Pemeriksaan menurut jenis input</caption>
        <thead><tr><th scope="col">Jenis input</th><th scope="col">Jumlah</th></tr></thead>
        <tbody>
            @forelse($breakdown['inputs'] as $key => $count)
                <tr><th scope="row">{{ \App\View\Presenters\DetectionPresentation::inputType($key) }}</th><td>{{ $count }}</td></tr>
            @empty
                <tr><td colspan="2">Belum ada pemeriksaan dalam jendela ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($mode === 'failures')
    <table class="hl-admin-data-table" data-admin-breakdown="failures">
        <caption>Event tahap yang berakhir gagal</caption>
        <thead><tr><th scope="col">Tahap</th><th scope="col">Kegagalan</th></tr></thead>
        <tbody>
            @forelse($breakdown['failures'] as $key => $count)
                <tr><th scope="row">{{ \App\Enums\ProcessingStage::tryFrom($key)?->label() ?? 'Tahap tidak dikenali' }}</th><td>{{ $count }}</td></tr>
            @empty
                <tr><td colspan="2">Tidak ada event gagal dalam jendela ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@else
    <table class="hl-admin-data-table" data-admin-breakdown="feedback">
        <caption>Penilaian pengguna terhadap prediksi</caption>
        <thead><tr><th scope="col">Penilaian</th><th scope="col">Jumlah</th></tr></thead>
        <tbody>
            <tr><th scope="row">Sesuai</th><td>{{ $breakdown['feedback']['correct'] }}</td></tr>
            <tr><th scope="row">Tidak sesuai</th><td>{{ $breakdown['feedback']['incorrect'] }}</td></tr>
        </tbody>
    </table>
@endif
