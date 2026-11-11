@extends('layouts.app')

@section('title', 'Tentang Hoaxlin — hoaxlin.id')
@section('description', 'Hoaxlin membantu membaca indikasi hoaks melalui prediksi model dan referensi terkait. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
<article class="container-app hl-reading-page" aria-labelledby="about-title">
    <header class="hl-reading-intro">
        <h1 id="about-title" class="hl-heading-editorial">Tentang Hoaxlin</h1>
        <p>Hoaxlin membantu Anda meninjau informasi melalui prediksi <x-ui.term key="indobert">model analisis (IndoBERT)</x-ui.term>, referensi terkait, dan penjelasan bila tersedia. Tujuannya memberi konteks untuk pemeriksaan lebih lanjut.</p>
        <p class="hl-reading-note">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
    </header>

    <section class="hl-reading-section" aria-labelledby="audience-title">
        <h2 id="audience-title">Siapa yang dapat menggunakan?</h2>
        <p>Tamu dapat memeriksa teks tanpa akun. Pengguna yang sudah masuk dapat menggunakan teks, tautan artikel, gambar, video unggahan, dan URL langsung ke file audio/video yang didukung.</p>
        <p>Riwayat, unduhan seluruh riwayat CSV, dan umpan balik memerlukan akun dengan email terverifikasi. Halaman media sosial dan pemutar video tidak didukung sebagai tautan media.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="principles-title">
        <h2 id="principles-title">Transparansi dan akses data</h2>
        <p>Label dan <x-ui.term key="confidence">skor keyakinan model</x-ui.term> adalah keluaran model, bukan kepastian faktual. Referensi berasal dari <x-ui.term key="knowledge_base">basis pengetahuan</x-ui.term> lokal; keterkaitannya tidak otomatis mendukung prediksi. Hasil menjelaskan ketika referensi atau penjelasan belum tersedia.</p>
        <p>Hasil akun dibatasi ke pemilik dan admin sesuai izin aplikasi. Hasil tamu memakai izin akses dalam sesi; ID pemeriksaan saja bukan tautan berbagi publik. Media diakses melalui jalur khusus yang memeriksa izin.</p>
        <p><x-ui.term key="ocr">Pembacaan teks gambar (OCR)</x-ui.term>, <x-ui.term key="transkripsi">transkripsi</x-ui.term>, terjemahan, dan penjelasan dapat melibatkan layanan eksternal. Rincian praktik data tersedia dalam <x-ui.link :href="route('kebijakan-privasi')">Kebijakan privasi</x-ui.link>.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="technology-title">
        <h2 id="technology-title">Teknologi dan perannya</h2>
        <dl class="hl-reading-definitions">
            <div>
                <dt><x-ui.term key="indobert">IndoBERT</x-ui.term></dt>
                <dd><x-ui.term key="classifier">Classifier</x-ui.term> untuk teks Indonesia, termasuk teks yang telah diterjemahkan. Model memberikan prediksi dan <x-ui.term key="confidence">skor keyakinan</x-ui.term>; layanan pendukung tidak mengganti label model.</dd>
            </div>
            <div>
                <dt><x-ui.term key="retrieval">Pencarian rujukan (Retrieval)</x-ui.term> lokal</dt>
                <dd><x-ui.term key="rag">RAG</x-ui.term> mencari referensi terkait dari dokumen yang tersedia. Cakupannya terbatas pada <x-ui.term key="knowledge_base">basis pengetahuan</x-ui.term> saat analisis, bukan pencarian seluruh internet.</dd>
            </div>
            <div>
                <dt>OpenAI</dt>
                <dd>Mendukung <x-ui.term key="ocr">OCR</x-ui.term>, <x-ui.term key="transkripsi">transkripsi</x-ui.term>, terjemahan Inggris ke Indonesia, dan penjelasan berdasarkan input serta hasil yang tersedia. Gangguan penjelasan tidak membatalkan klasifikasi yang sudah selesai.</dd>
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
