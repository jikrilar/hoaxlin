@php($trend = $this->getTrend())
<x-filament-widgets::widget class="hl-admin-trend">
    {{-- Retain Filament's chart loader, palette, polling, checksum and lifecycle. --}}
    @include('filament-widgets::chart-widget')
    <noscript><p class="hl-admin-note" data-admin-chart-nojs>Grafik memerlukan JavaScript. Tabel data berikut tetap dapat dibuka.</p></noscript>
    <details class="hl-admin-data-disclosure" wire:ignore.self>
        <summary>Tabel data tren · 30 hari</summary>
        @if(!$trend['available'])
            <p class="hl-admin-note">Data tren belum tersedia (n/a). Muat ulang halaman untuk mencoba kembali.</p>
        @else
            <p class="hl-admin-note">{{ $trend['start'] }}–{{ $trend['end'] }} · {{ $trend['timezone'] }}. Grafik dan tabel memakai data yang sama.</p>
            <table class="hl-admin-data-table" data-admin-trend>
                <caption>Jumlah submission menurut tanggal pengiriman · semua status</caption>
                <thead><tr><th scope="col">Tanggal</th><th scope="col">Semua submission</th><th scope="col">Dengan prediksi hoaks tersimpan</th></tr></thead>
                <tbody>
                    @foreach($trend['rows'] as $row)
                        <tr data-date="{{ $row['date'] }}"><th scope="row">{{ $row['label'] }}</th><td>{{ number_format($row['total'], 0, ',', '.') }}</td><td>{{ number_format($row['hoax'], 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </details>
</x-filament-widgets::widget>
