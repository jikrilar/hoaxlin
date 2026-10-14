@extends('layouts.app')

@section('title', 'Tentang Hoaxlin — hoaxlin.id')
@section('description', 'Hoaxlin membantu membaca indikasi hoaks melalui prediksi model dan referensi terkait. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
<article class="container-app hl-reading-page" aria-labelledby="about-title">
    <header class="hl-reading-intro">
        <h1 id="about-title" class="hl-heading-editorial">Tentang Hoaxlin</h1>
        <p>Hoaxlin membantu kamu meninjau informasi melalui prediksi IndoBERT, referensi terkait, dan penjelasan bila tersedia. Tujuannya memberi konteks untuk pemeriksaan lebih lanjut.</p>
        <p class="hl-reading-note">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
    </header>

    <section class="hl-reading-section" aria-labelledby="audience-title">
        <h2 id="audience-title">Siapa yang dapat menggunakan?</h2>
        <p>Tamu dapat memeriksa teks tanpa akun. Pengguna yang sudah masuk dapat menggunakan teks, tautan artikel, gambar, video unggahan, dan URL langsung ke file audio/video yang didukung.</p>
        <p>Riwayat, unduhan seluruh riwayat CSV, dan feedback memerlukan akun dengan email terverifikasi. Halaman media sosial dan pemutar video tidak didukung sebagai tautan media.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="principles-title">
        <h2 id="principles-title">Transparansi dan akses data</h2>
        <p>Label dan skor keyakinan adalah keluaran model, bukan kepastian faktual. Referensi berasal dari basis pengetahuan lokal; keterkaitannya tidak otomatis mendukung prediksi. Hasil menjelaskan ketika referensi atau penjelasan belum tersedia.</p>
        <p>Hasil akun dibatasi ke pemilik dan admin sesuai izin aplikasi. Hasil tamu memakai izin akses dalam sesi; ID submission saja bukan tautan berbagi publik. Media diakses melalui endpoint yang memeriksa izin.</p>
        <p>OCR, transkripsi, terjemahan, dan penjelasan dapat melibatkan layanan eksternal. Rincian praktik data tersedia dalam <x-ui.link :href="route('kebijakan-privasi')">Kebijakan privasi</x-ui.link>.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="technology-title">
        <h2 id="technology-title">Teknologi dan perannya</h2>
        <dl class="hl-reading-definitions">
            <div>
                <dt>IndoBERT</dt>
                <dd>Classifier untuk teks Indonesia, termasuk teks yang telah diterjemahkan. Model memberikan prediksi dan skor keyakinan; layanan pendukung tidak mengganti label classifier.</dd>
            </div>
            <div>
                <dt>Retrieval basis pengetahuan lokal</dt>
                <dd>RAG mencari referensi terkait dari dokumen yang tersedia. Cakupannya terbatas pada basis pengetahuan saat analisis, bukan pencarian seluruh Internet.</dd>
            </div>
            <div>
                <dt>OpenAI</dt>
                <dd>Mendukung OCR, transkripsi, terjemahan Inggris ke Indonesia, dan penjelasan berdasarkan input serta hasil yang tersedia. Gangguan penjelasan tidak membatalkan klasifikasi yang sudah selesai.</dd>
            </div>
            <div>
                <dt>Aplikasi web</dt>
                <dd>Laravel mengelola validasi, akun, izin akses, dan penyimpanan. Livewire memperbarui status pemrosesan; Filament menyediakan administrasi.</dd>
            </div>
        </dl>
        <p>Detail versi model tersimpan dapat ditinjau pada hasil pemeriksaan. Klaim evaluasi perlu dibaca bersama model, dataset, dan versi yang dievaluasi; tidak berlaku sebagai jaminan akurasi untuk semua informasi.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="project-title">
        <h2 id="project-title">Konteks proyek</h2>
        <p>Hoaxlin dikembangkan sebagai proyek Tugas Akhir D3 Teknik Komputer. Aplikasi ini menyediakan bantuan analisis; pengguna tetap perlu memeriksa informasi terhadap sumber terpercaya.</p>
        <div class="hl-reading-actions">
            <x-ui.link :href="route('cara-kerja')" variant="secondary">Cara kerja Hoaxlin</x-ui.link>
            <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
        </div>
    </section>
</article>
@endsection
