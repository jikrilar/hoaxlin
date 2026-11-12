@extends('layouts.app')

@section('title', 'Periksa informasi — hoaxlin.id')
@section('description', 'Periksa informasi dengan prediksi model dan rujukan terkait. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
@php
    $previousRawInput = old('raw_input');
    $previousRawInput = is_string($previousRawInput) ? $previousRawInput : '';
    $previousSourceUrl = old('source_url');
    $previousSourceUrl = is_string($previousSourceUrl) ? $previousSourceUrl : '';
    $previousInputType = old('input_type');
    $previousInputType = is_string($previousInputType) ? $previousInputType : '';
    $imageOptions = $submissionOptions['image'];
    $videoOptions = $submissionOptions['video'];
    $videoUrlOptions = $submissionOptions['videoUrl'];
@endphp

<div class="hl-submission-page container-app" data-page="submission">
    <header class="hl-hero" aria-labelledby="hero-title">
        <h1 id="hero-title" class="hl-heading-editorial">Periksa informasi sebelum Anda membagikannya.</h1>
        <p class="hl-hero__lead">Tempel informasi untuk mendapatkan prediksi model dan rujukan terkait, tanpa perlu akun.</p>
        <p class="hl-hero__note">Hasil berupa prediksi model, bukan keputusan akhir.</p>
    </header>

    <section id="cek-berita" class="hl-submission-surface" aria-labelledby="submission-title">
        <h2 id="submission-title">Pemeriksaan informasi</h2>
        @include('partials.submission.errors')
        @guest
            <p class="hl-submission-access">Tamu dapat memeriksa teks tanpa akun. <x-ui.link :href="route('login')">Masuk</x-ui.link> untuk menggunakan tautan artikel, gambar, atau video.</p>
        @endguest

        @include('partials.submission.form', [
            'previousRawInput' => $previousRawInput,
            'previousSourceUrl' => $previousSourceUrl,
            'previousInputType' => $previousInputType,
            'activeTabKey' => $activeTabKey,
            'inputTabs' => $inputTabs,
        ])

        <x-ui.disclaimer class="hl-submission-note">Periksa kembali informasi melalui sumber terpercaya sebelum membagikannya.</x-ui.disclaimer>
    </section>

    <section class="hl-landing-section" aria-labelledby="steps-title">
        <h2 id="steps-title">Tiga langkah</h2>
        <x-ui.stepper label="Alur pemeriksaan informasi" :steps="[
            ['key' => 'kirim', 'label' => 'Kirim'],
            ['key' => 'analisis', 'label' => 'Analisis'],
            ['key' => 'baca', 'label' => 'Baca hasil'],
        ]" />
        <p class="hl-landing-note">Pemrosesan berjalan di latar belakang; halaman hasil menampilkan tahap yang tercatat, bukan perkiraan waktu.</p>
    </section>

    <section class="hl-landing-section" aria-labelledby="example-title">
        <h2 id="example-title">Contoh tampilan hasil</h2>
        <div class="hl-landing-grid">
            <x-ui.card class="hl-landing-example" header="Contoh tampilan, bukan hasil nyata" footer="Ilustrasi statis">
                <p class="hl-landing-example__label">Prediksi model: <x-ui.badge tone="warning">Informasi belum terverifikasi</x-ui.badge></p>
                <p><strong>Skor keyakinan model:</strong> contoh skor, bukan persentase kebenaran.</p>
                <p><strong>Rujukan:</strong> kutipan ringkas dari sumber yang tercatat di basis pengetahuan.</p>
                <p class="hl-landing-note">Hasil contoh ini tidak berasal dari pemeriksaan pengguna dan tidak merujuk sumber nyata.</p>
            </x-ui.card>
        </div>
    </section>

    <section class="hl-landing-section" aria-labelledby="types-title">
        <h2 id="types-title">Jenis input yang didukung</h2>
        <ul class="hl-landing-card-list" role="list">
            @foreach([
                ['title' => 'Teks', 'body' => 'Tempel isi informasi, 50–50.000 karakter.', 'guest' => true, 'tab' => null],
                ['title' => 'Tautan artikel', 'body' => 'URL HTTP/HTTPS halaman yang dapat dibaca server.', 'guest' => false, 'tab' => 'url'],
                ['title' => 'Gambar', 'body' => strtoupper(implode(', ', $imageOptions['extensions'])).'; maksimal '.$imageOptions['maxSizeLabel'].'.', 'guest' => false, 'tab' => 'gambar'],
                ['title' => 'Video', 'body' => strtoupper(implode(', ', $videoOptions['extensions'])).'; maksimal '.$videoOptions['maxSizeLabel'].' dan '.$videoOptions['maxDurationSeconds'].' detik.', 'guest' => false, 'tab' => 'video'],
            ] as $type)
                <li class="hl-landing-card">
                    <h3>{{ $type['title'] }}</h3>
                    <p>{{ $type['body'] }}</p>
                    @if($type['guest'])
                        <p class="hl-landing-note">Tersedia tanpa akun.</p>
                    @elseif(auth()->check())
                        <p class="hl-landing-note">Tersedia setelah masuk.</p>
                    @else
                        <p class="hl-landing-lock"><x-ui.link :href="route('login')">Masuk untuk memakai</x-ui.link></p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <section class="hl-landing-section" aria-labelledby="meaning-title">
        <h2 id="meaning-title">Apa arti hasilnya</h2>
        <ul class="hl-landing-card-list" role="list">
            <li>
                <article class="hl-landing-card">
                    <h3>Valid</h3>
                    <p><x-ui.badge tone="success">Prediksi model: valid</x-ui.badge></p>
                    <p>Model menilai pola teks lebih mirip informasi valid. Ini tetap prediksi, bukan bukti kebenaran.</p>
                </article>
            </li>
            <li>
                <article class="hl-landing-card">
                    <h3>Hoaks</h3>
                    <p><x-ui.badge tone="danger">Prediksi model: hoaks</x-ui.badge></p>
                    <p>Model menilai pola teks lebih mirip hoaks. Periksa sumber asli sebelum menyimpulkan.</p>
                </article>
            </li>
            <li>
                <article class="hl-landing-card">
                    <h3>Belum terverifikasi</h3>
                    <p><x-ui.badge tone="warning">Informasi belum terverifikasi</x-ui.badge></p>
                    <p>Skor keyakinan model belum memenuhi batas, sehingga sistem tidak memilih valid atau hoaks. Ini bukan bukti bahwa informasi benar atau palsu.</p>
                </article>
            </li>
        </ul>
    </section>

    <section class="hl-landing-section" aria-labelledby="faq-title">
        <h2 id="faq-title">Pertanyaan umum</h2>
        @php
            $mediaRetentionHours = max(1, (int) config('data_retention.media_hours', 24));
        @endphp
        <x-ui.disclosure title="Apakah data saya aman?">
            <p>Input dan hasil disimpan di basis data aplikasi dan hanya dapat dibuka melalui jalur yang memeriksa izin. Media disimpan di penyimpanan privat, bukan tautan publik. Tidak ada input yang otomatis menjadi data pelatihan model. Rincian ada di <x-ui.link :href="route('kebijakan-privasi')">Kebijakan Privasi</x-ui.link>.</p>
        </x-ui.disclosure>
        <x-ui.disclosure title="Berapa lama hasil tersimpan?">
            <p>Teks, hasil, dan riwayat tidak dihapus oleh pembersihan media. File media pemeriksaan yang selesai atau gagal menjadi layak dihapus setelah {{ $mediaRetentionHours }} jam; penghapusan bergantung pada penjadwal yang berjalan, jadi bukan jaminan tepat waktu.</p>
        </x-ui.disclosure>
        <x-ui.disclosure title="Kenapa hasilnya “belum terverifikasi”?">
            <p>Label itu muncul saat skor keyakinan model belum melewati batas yang ditetapkan. Sistem memilih tidak menebak. Hasil seperti ini bukan bukti bahwa informasi benar atau palsu, dan bukan kelas latih ketiga.</p>
        </x-ui.disclosure>
        <x-ui.disclosure title="Bisakah prediksi model salah?">
            <p>Bisa. Model membaca pola bahasa dan bisa keliru pada konteks, sarkasme, atau teks yang tidak lengkap. Skor keyakinan bukan persentase kebenaran berita. Gunakan hasil sebagai petunjuk awal bersama sumber terpercaya.</p>
        </x-ui.disclosure>
        <x-ui.disclosure title="Bahasa apa yang didukung?">
            <p>Teks Indonesia dianalisis langsung. Teks Inggris diterjemahkan ke Indonesia lebih dulu. Penulisan campuran atau bahasa lain dapat menghasilkan analisis yang kurang tepat karena kualitas terjemahan memengaruhi hasil.</p>
        </x-ui.disclosure>
    </section>


        <h2 id="limits-title">Batasan dan privasi</h2>
        <ul>
            <li>Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</li>
            <li>Rujukan berasal dari basis pengetahuan lokal, bukan pencarian seluruh internet.</li>
            <li>Riwayat, ekspor CSV, dan umpan balik memerlukan akun dengan email terverifikasi.</li>
        </ul>
        <x-ui.link :href="route('kebijakan-privasi')">Baca Kebijakan Privasi</x-ui.link>
    </section>
</div>
@endsection
