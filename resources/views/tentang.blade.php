@extends('layouts.app')

@section('title', 'Tentang Hoaxlin — hoaxlin.id')
@section('description', 'Hoaxlin membantu membaca indikasi hoaks melalui prediksi model dan referensi terkait. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
<article class="container-app hl-reading-page" aria-labelledby="about-title">
    <header class="hl-reading-intro">
        <h1 id="about-title" class="hl-heading-editorial">Tentang Hoaxlin</h1>
        <p>Hoaxlin adalah alat bantu untuk meninjau informasi: ia memberi prediksi pola bahasa, rujukan terkait, dan penjelasan bila tersedia, lalu menyerahkan penilaian akhir kepada Anda.</p>
        <x-ui.disclaimer variant="full" class="hl-reading-note" />
    </header>

    <section class="hl-reading-section" aria-labelledby="purpose-title">
        <h2 id="purpose-title">Tujuan Hoaxlin</h2>
        <p>Tujuan kami sederhana: membuat pemeriksaan informasi lebih mudah dimulai, tanpa berpura-pura menggantikan verifikasi manusia.</p>
        <ul class="hl-reading-list">
            <li>Memberi gambaran awal dari pola teks yang sudah dikenal model.</li>
            <li>Menunjukkan rujukan yang tersimpan agar Anda punya titik awal pemeriksaan.</li>
            <li>Menyatakan dengan jelas kapan model tidak cukup yakin atau data pendukung tidak tersedia.</li>
        </ul>
        <p class="hl-reading-note">Hoaxlin tidak menentukan benar atau salah, tidak menghapus konten, dan tidak menilai kredibilitas penulis.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="audience-title">
        <h2 id="audience-title">Siapa yang dapat menggunakan?</h2>
        <p>Tamu dapat memeriksa teks tanpa akun. Pengguna yang sudah masuk dapat menggunakan teks, tautan artikel, gambar, video unggahan, dan URL langsung ke file audio/video yang didukung.</p>
        <p>Riwayat, unduhan seluruh riwayat CSV, dan umpan balik memerlukan akun dengan email terverifikasi. Halaman media sosial dan pemutar video tidak didukung sebagai tautan media.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="privacy-title">
        <h2 id="privacy-title">Privasi dan akses data</h2>
        <p>Hasil akun dibatasi ke pemilik dan admin sesuai izin aplikasi. Hasil tamu memakai izin akses dalam sesi; ID pemeriksaan saja bukan tautan berbagi publik. Media diakses melalui jalur khusus yang memeriksa izin.</p>
        <p>Pembacaan teks gambar, pengubahan suara menjadi teks, terjemahan, dan penjelasan dapat melibatkan layanan eksternal. Rincian praktik data tersedia dalam <x-ui.link :href="route('kebijakan-privasi')">Kebijakan privasi</x-ui.link>.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="limits-title">
        <h2 id="limits-title">Keterbatasan</h2>
        <ul class="hl-reading-list">
            <li>Prediksi berasal dari pola teks. Sarkasme, konteks yang terpotong, atau bahasa campuran dapat membuatnya keliru.</li>
            <li>Rujukan hanya berasal dari kumpulan dokumen yang tersedia saat analisis, bukan pencarian seluruh internet; keterkaitan sumber tidak otomatis mendukung prediksi.</li>
            <li>Penjelasan bisa belum siap atau tidak tersedia; gangguan penjelasan tidak membatalkan klasifikasi yang sudah selesai.</li>
            <li>Skor keyakinan bukan persentase kebenaran berita dan bukan ukuran kredibilitas sumber.</li>
        </ul>
    </section>

    <section class="hl-reading-section" aria-labelledby="project-title">
        <h2 id="project-title">Konteks proyek</h2>
        <p>Hoaxlin dikembangkan sebagai proyek Tugas Akhir D3 Teknik Komputer. Aplikasi ini menyediakan bantuan analisis; pengguna tetap perlu memeriksa informasi terhadap sumber terpercaya.</p>
        <p>Model analisis yang dipakai adalah IndoBERT yang sudah dibekukan untuk rilis ini. Halaman <x-ui.link :href="route('cara-kerja')">Cara kerja Hoaxlin</x-ui.link> menjelaskan setiap tahap pemrosesan secara rinci.</p>
        <div class="hl-reading-actions">
            <x-ui.link :href="route('cara-kerja')" variant="secondary">Cara kerja Hoaxlin</x-ui.link>
            <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
        </div>
    </section>

    <section class="hl-reading-section" aria-labelledby="faq-title">
        <h2 id="faq-title">Pertanyaan umum</h2>
        <x-ui.disclosure title="Apakah Hoaxlin memutuskan informasi itu hoaks?">
            <p>Tidak. Hoaxlin menampilkan prediksi pola bahasa beserta skor keyakinan model. Keputusan akhir tetap memerlukan pemeriksaan sumber oleh Anda.</p>
        </x-ui.disclosure>
        <x-ui.disclosure title="Kenapa saya tidak bisa memakai gambar atau video?">
            <p>Jenis input selain teks memerlukan akun dengan email terverifikasi. Tamu hanya dapat mengirim teks; masuk untuk membuka jenis lainnya.</p>
        </x-ui.disclosure>
        <x-ui.disclosure title="Apakah hasil saya bisa dibagikan lewat tautan?">
            <p>ID pemeriksaan bukan tautan berbagi publik. Hasil akun hanya dapat dibuka pemilik dan admin sesuai izin; hasil tamu terikat pada sesi pembuatnya.</p>
        </x-ui.disclosure>
        <x-ui.disclosure title="Apakah data saya dipakai untuk melatih model?">
            <p>Tidak secara otomatis. Input dan umpan balik tidak menjadi data pelatihan tanpa proses kurasi terpisah. Rincian ada di Kebijakan privasi.</p>
        </x-ui.disclosure>
    </section>
</article>
@endsection
