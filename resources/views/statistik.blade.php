@extends('layouts.app')

@section('title', 'Statistik pemeriksaan — hoaxlin.id')
@section('description', 'Lihat tren submission dan distribusi label tersimpan di Hoaxlin beserta konteks populasinya. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

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
@endphp
<div class="container-app hl-statistics-page" data-page="statistics">
    <header class="hl-statistics-intro">
        <h1>Statistik pemeriksaan</h1>
        <p>Agregat aktivitas Hoaxlin. Jumlah submission dan label tersimpan menggambarkan penggunaan aplikasi, bukan prevalensi hoaks di masyarakat atau akurasi model.</p>
        <p class="hl-statistics-note">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
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
        <p>Periode ini memuat <strong>{{ number_format($totalInPeriod, 0, ',', '.') }} submission</strong> dari semua status, termasuk menunggu, diproses, selesai, dan gagal. Bulan berjalan belum berakhir.</p>
        <p class="hl-statistics-note">Setiap bulan dihitung sejak awal bulan sampai sebelum awal bulan berikutnya, menurut zona waktu aplikasi. Kolom label menghitung submission yang memiliki hasil dengan label tersebut; tidak dibatasi ke status selesai. Label meragukan dan submission tanpa hasil tidak termasuk kolom Hoaks atau Valid.</p>
        @if($totalInPeriod === 0)
            <x-ui.empty-state message="Belum ada submission dalam periode 12 bulan ini. Nilai nol tetap ditampilkan pada tabel." />
        @endif
        <p id="trend-table-hint" class="hl-statistics-note">Pada layar sempit, geser tabel untuk membaca semua kolom. Gunakan tombol panah saat area tabel mendapat fokus.</p>
        <div class="hl-statistics-table-scroll" tabindex="0" role="region" aria-labelledby="trend-table-caption" aria-describedby="trend-table-hint">
            <table id="statistics-trend-table" class="hl-statistics-table hl-statistics-table--trend">
                <caption id="trend-table-caption">Jumlah submission per bulan dan label tersimpan</caption>
                <thead><tr><th scope="col">Bulan</th><th scope="col">Total submission</th><th scope="col">Dengan label Hoaks</th><th scope="col">Dengan label Valid</th></tr></thead>
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
        @if($totalInPeriod > 0)
            <figure class="hl-statistics-chart" data-statistics-chart-panel hidden>
                <figcaption id="statistics-chart-caption">Grafik tren aktivitas — data yang sama dengan tabel di atas</figcaption>
                <div class="hl-statistics-chart__canvas">
                    <canvas data-statistics-trend="{{ json_encode($chartData) }}" role="img" aria-labelledby="statistics-chart-caption" aria-describedby="trend-table-caption">Nilai lengkap tersedia pada tabel jumlah submission per bulan.</canvas>
                </div>
            </figure>
        @endif
    </section>

    <section class="hl-statistics-section" aria-labelledby="statistics-labels-heading">
        <h2 id="statistics-labels-heading">Distribusi label tersimpan</h2>
        <p>Agregat dari <strong>{{ number_format($labelCount, 0, ',', '.') }} hasil dengan label tersimpan</strong> yang terhubung ke submission, tanpa batas periode 12 bulan dan tanpa filter status. Submission tanpa hasil tidak dihitung di sini.</p>
        <p class="hl-statistics-note">Distribusi ini menggabungkan versi model yang tersimpan. Jumlah label bukan distribusi topik, jumlah fakta terverifikasi, atau ukuran kualitas model.</p>
        @if($labelCount === 0)
            <x-ui.empty-state message="Belum ada hasil dengan label tersimpan. Angka nol berasal dari agregat data aplikasi." />
        @endif
        <table id="statistics-label-table" class="hl-statistics-table">
            <caption>Jumlah hasil menurut label tersimpan, seluruh periode</caption>
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
        <p class="hl-statistics-note">Label meragukan adalah abstention: Informasi belum terverifikasi oleh sumber terpercaya. Ini bukan bukti bahwa informasi benar atau palsu.</p>
        <div class="hl-statistics-actions">
            <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
            <x-ui.link :href="route('cara-kerja')" variant="secondary">Cara kerja Hoaxlin</x-ui.link>
        </div>
    </section>
</div>
@endsection
