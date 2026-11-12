@extends('layouts.app')

@section('title', 'Statistik pemeriksaan — hoaxlin.id')
@section('description', 'Lihat tren pemeriksaan dan distribusi label tersimpan di Hoaxlin beserta konteks populasinya. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
@php
    $totalInPeriod = (int) $trend->sum('total');
    $labelCount = (int) array_sum($byTopic);
    $labels = ['valid', 'hoax', 'meragukan'];
    $otherLabels = array_diff_key($byTopic, array_flip($labels));
    $chartData = $trend->map(fn ($month) => [
        'label' => $month['display_label'],
        'total' => $month['total'], 'hoax' => $month['hoax'], 'valid' => $month['valid'],
    ]);
    // Label distribution mirrors the table below, including its text labels and tone.
    $labelChartData = collect($labels)->map(fn ($label) => [
        'label' => \App\View\Presenters\DetectionPresentation::label($label)['text'],
        'count' => (int) ($byTopic[$label] ?? 0),
        'tone' => \App\View\Presenters\DetectionPresentation::label($label)['tone'],
    ])->values();
@endphp
<div class="container-app hl-statistics-page" data-page="statistics">
    <header class="hl-statistics-intro">
        <h1>Statistik pemeriksaan</h1>
        <p>Agregat aktivitas Hoaxlin. Jumlah pemeriksaan dan label tersimpan menggambarkan penggunaan aplikasi, bukan prevalensi hoaks di masyarakat atau akurasi model.</p>
        <x-ui.disclaimer class="hl-statistics-note">Jumlah pemeriksaan menggambarkan penggunaan aplikasi, bukan prevalensi hoaks di masyarakat.</x-ui.disclaimer>
    </header>

    <section class="hl-statistics-section" aria-labelledby="statistics-trend-heading">
        <h2 id="statistics-trend-heading">Tren aktivitas 12 bulan</h2>
        <dl class="hl-statistics-context">
            <div>
                <dt>Periode bulan</dt>
                <dd><time datetime="{{ $period['start']->format('Y-m') }}">{{ $period['start']->locale('id')->translatedFormat('F Y') }}</time> – <time datetime="{{ $period['end_exclusive']->subMonth()->format('Y-m') }}">{{ $period['end_exclusive']->subMonth()->locale('id')->translatedFormat('F Y') }}</time></dd>
            </div>
            <div><dt>Zona waktu</dt><dd>{{ $period['timezone'] }}</dd></div>
            <div><dt>Data dihitung pada</dt><dd><time datetime="{{ $period['generated_at']->toIso8601String() }}">{{ $period['generated_at']->format('d/m/Y H:i') }}</time></dd></div>
        </dl>
        <p>Periode ini memuat <strong>{{ number_format($totalInPeriod, 0, ',', '.') }} pemeriksaan</strong> dari semua status, termasuk menunggu, diproses, selesai, dan gagal. Bulan berjalan belum berakhir.</p>
        @if($totalInPeriod === 0)
            <x-ui.empty-state message="Belum ada pemeriksaan dalam periode 12 bulan ini. Nilai nol tetap ditampilkan pada tabel." />
        @endif
        {{-- T2.9: the chart leads; the native table stays one disclosure away for everyone else. --}}
        @if($totalInPeriod > 0)
            <figure class="hl-statistics-chart" data-statistics-chart-panel hidden>
                <figcaption id="statistics-chart-caption">Grafik tren aktivitas — data yang sama dengan tabel di bawah</figcaption>
                <div class="hl-statistics-chart__canvas">
                    <canvas data-statistics-trend="{{ json_encode($chartData) }}" role="img" aria-labelledby="statistics-chart-caption" aria-describedby="trend-table-caption">Nilai lengkap tersedia pada tabel jumlah pemeriksaan per bulan.</canvas>
                </div>
            </figure>
        @endif
        <p id="trend-table-hint" class="hl-statistics-note">Pada layar sempit, geser tabel untuk membaca semua kolom. Gunakan tombol panah saat area tabel mendapat fokus.</p>
        <details class="hl-disclosure hl-statistics-disclosure">
            <summary class="hl-disclosure__summary">Lihat data tabel</summary>
            <div class="hl-disclosure__content">
                <div class="hl-statistics-table-scroll" tabindex="0" role="region" aria-labelledby="trend-table-caption" aria-describedby="trend-table-hint">
                    <table id="statistics-trend-table" class="hl-statistics-table hl-statistics-table--trend">
                        <caption id="trend-table-caption">Jumlah pemeriksaan per bulan dan label tersimpan</caption>
                        <thead><tr><th scope="col">Bulan</th><th scope="col">Total pemeriksaan</th><th scope="col">Dengan label Hoaks</th><th scope="col">Dengan label Valid</th></tr></thead>
                        <tbody>
                            @foreach($trend as $month)
                                <tr data-statistics-month="{{ $month['month'] }}">
                                    <th scope="row"><time datetime="{{ $month['month'] }}">{{ $month['display_label'] }}</time></th>
                                    <td>{{ number_format((int) $month['total'], 0, ',', '.') }}</td>
                                    <td>{{ number_format((int) $month['hoax'], 0, ',', '.') }}</td>
                                    <td>{{ number_format((int) $month['valid'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        <x-ui.disclosure title="Cara angka ini dihitung" class="hl-statistics-disclosure">
            <p>Setiap bulan dihitung sejak awal bulan sampai sebelum awal bulan berikutnya, menurut zona waktu {{ $period['timezone'] }}. Rentang bersifat setengah-terbuka, sehingga satu pemeriksaan tidak pernah dihitung di dua bulan.</p>
            <p>Kolom total memuat pemeriksaan dari semua status, termasuk menunggu, diproses, selesai, dan gagal. Kolom label menghitung pemeriksaan yang memiliki hasil dengan label tersebut; tidak dibatasi ke status selesai. Status belum terverifikasi dan pemeriksaan tanpa hasil tidak termasuk kolom Hoaks atau Valid.</p>
            <p>Distribusi label di bagian bawah dihitung dari seluruh hasil tersimpan, tanpa batas periode dan tanpa filter status.</p>
        </x-ui.disclosure>
    </section>

    <section class="hl-statistics-section" aria-labelledby="statistics-labels-heading">
        <h2 id="statistics-labels-heading">Distribusi label tersimpan</h2>
        <p>Agregat dari <strong>{{ number_format($labelCount, 0, ',', '.') }} hasil dengan label tersimpan</strong> yang terhubung ke pemeriksaan, tanpa batas periode 12 bulan dan tanpa filter status. Pemeriksaan tanpa hasil tidak dihitung di sini.</p>
        <p class="hl-statistics-note">Distribusi ini menggabungkan versi model yang tersimpan. Jumlah label bukan distribusi topik, jumlah fakta terverifikasi, atau ukuran kualitas model.</p>
        @if($labelCount === 0)
            <x-ui.empty-state message="Belum ada hasil dengan label tersimpan. Angka nol berasal dari agregat data aplikasi." />
        @endif
        @if($labelCount > 0)
            <figure class="hl-statistics-chart" data-statistics-labels-panel hidden>
                <figcaption id="statistics-labels-caption">Distribusi label tersimpan — data yang sama dengan tabel di bawah</figcaption>
                <div class="hl-statistics-chart__canvas hl-statistics-chart__canvas--compact">
                    <canvas data-statistics-labels="{{ json_encode($labelChartData) }}" role="img" aria-labelledby="statistics-labels-caption" aria-describedby="statistics-label-table-caption">Nilai lengkap tersedia pada tabel distribusi label.</canvas>
                </div>
            </figure>
        @endif
        <table id="statistics-label-table" class="hl-statistics-table">
            <caption id="statistics-label-table-caption">Jumlah hasil menurut label tersimpan, seluruh periode</caption>
            <thead><tr><th scope="col">Label prediksi</th><th scope="col">Jumlah hasil</th></tr></thead>
            <tbody>
                @foreach($labels as $label)
                    <tr data-statistics-label="{{ $label }}"><th scope="row">{{ \App\View\Presenters\DetectionPresentation::label($label)['text'] }}</th><td>{{ number_format((int) ($byTopic[$label] ?? 0), 0, ',', '.') }}</td></tr>
                @endforeach
                @if($otherLabels !== [])
                    <tr><th scope="row">Label lain atau belum tersedia</th><td>{{ number_format((int) array_sum($otherLabels), 0, ',', '.') }}</td></tr>
                @endif
            </tbody>
        </table>
        <p class="hl-statistics-note">Status <x-ui.term key="abstention">belum terverifikasi</x-ui.term> berarti informasi belum diverifikasi oleh sumber terpercaya. Ini bukan bukti bahwa informasi benar atau palsu.</p>
        <x-ui.disclosure title="Tentang model" class="hl-statistics-disclosure">
            @php($activeModel = \App\Models\ModelVersion::currentActive())
            <p>Model aktif: <strong>{{ $activeModel?->version_name ?? 'Belum tersedia' }}</strong>.
                Versi dicatat saat hasil disimpan; agregat ini menggabungkan beberapa versi model.</p>
            <p>Tanggal evaluasi: <strong>{{ $activeModel?->updated_at?->format('d M Y') ?? 'Belum tersedia' }}</strong>.
                Halaman ini tidak menerbitkan angka akurasi produk; evaluasi model adalah bukti terbatas pada dataset uji, bukan jaminan atas informasi tertentu.</p>
        </x-ui.disclosure>

        <div class="hl-statistics-actions">
            <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
            <x-ui.link :href="route('cara-kerja')" variant="secondary">Cara kerja Hoaxlin</x-ui.link>
        </div>
    </section>
</div>
@endsection
