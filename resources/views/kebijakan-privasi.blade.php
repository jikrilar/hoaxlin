@extends('layouts.app')

@section('title', 'Kebijakan privasi — hoaxlin.id')
@section('description', 'Pelajari data yang diproses Hoaxlin, layanan pendukung, akses, retensi media, dan penghapusan akun. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
@php
    $mediaRetentionHours = max(1, (int) config('data_retention.media_hours', 24));
@endphp
<article class="container-app hl-reading-page" aria-labelledby="privacy-title">
    <header class="hl-reading-intro">
        <h1 id="privacy-title" class="hl-heading-editorial">Kebijakan privasi</h1>
        <p class="hl-reading-note">Terakhir diperbarui: <time datetime="2026-10-14">14 Oktober 2026</time></p>
        <p>Kebijakan ini menjelaskan data yang diproses saat kamu memakai Hoaxlin untuk memeriksa informasi, menggunakan akun, dan memberikan feedback.</p>
    </header>

    <section class="hl-reading-section" aria-labelledby="privacy-data-title">
        <h2 id="privacy-data-title">Data yang diproses</h2>
        <dl class="hl-reading-definitions">
            <div>
                <dt>Data akun</dt>
                <dd>Nama, alamat email, kata sandi yang disimpan sebagai hash, dan status verifikasi email untuk akses akun. Email digunakan untuk verifikasi dan pemulihan kata sandi melalui layanan email yang dikonfigurasi.</dd>
            </div>
            <div>
                <dt>Input pemeriksaan</dt>
                <dd>Teks, URL artikel atau media, serta gambar/video yang kamu unggah. File dari URL media yang didukung dapat diunduh dan disimpan untuk transkripsi.</dd>
            </div>
            <div>
                <dt>Hasil dan feedback</dt>
                <dd>Teks hasil ekstraksi atau transkripsi, terjemahan bila diperlukan, prediksi, skor keyakinan model, referensi terkait, penjelasan bila tersedia, dan feedback yang kamu kirim.</dd>
            </div>
            <div>
                <dt>Data operasional</dt>
                <dd>Status dan waktu pemrosesan, versi model/provider, catatan kegagalan, penggunaan layanan, dan aktivitas administrasi. Alamat IP digunakan untuk pembatasan permintaan; informasi sesi dan perangkat dapat tersimpan sesuai konfigurasi server.</dd>
            </div>
        </dl>
    </section>

    <section class="hl-reading-section" aria-labelledby="privacy-processing-title">
        <h2 id="privacy-processing-title">Pemrosesan dan layanan pendukung</h2>
        <p>Hoaxlin mengolah input menjadi teks, menerjemahkan bila diperlukan, mengirim teks analisis ke layanan IndoBERT, lalu mencari referensi melalui layanan retrieval basis pengetahuan lokal. Pencarian referensi tidak mencakup seluruh web.</p>
        <p>OpenAI API dapat menerima gambar untuk OCR, file media untuk transkripsi, teks untuk terjemahan Inggris ke Indonesia, serta prediksi, cuplikan teks, dan referensi untuk menyusun penjelasan. Konten diproses di server dan dapat dikirim ke layanan tersebut; pemrosesan tidak berlangsung hanya di perangkatmu.</p>
        <p>Penanganan data pada layanan eksternal mengikuti pengaturan dan ketentuan penyedia. Retensi media Hoaxlin tidak menetapkan retensi data di OpenAI. Baca <x-ui.link href="https://developers.openai.com/api/docs/guides/your-data" target="_blank">Penanganan data OpenAI API (tab baru)</x-ui.link> untuk rincian dari penyedia.</p>
        <p>Untuk URL artikel atau media, server menghubungi situs tujuan untuk mengambil konten. Layanan email dan penyimpanan juga mengikuti konfigurasi layanan yang digunakan Hoaxlin.</p>
        <p>Input dan feedback tidak otomatis menjadi data training model. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="privacy-access-title">
        <h2 id="privacy-access-title">Penyimpanan dan akses</h2>
        <p>Data akun, input, hasil, dan feedback disimpan dalam basis data aplikasi. File media disimpan pada penyimpanan yang digunakan aplikasi; media diakses melalui endpoint yang memeriksa izin, bukan tautan storage publik.</p>
        <p>Hasil akun dapat diakses oleh pemilik dan admin sesuai izin aplikasi. Riwayat dan ekspor CSV dibatasi ke akun peminta, termasuk ketika peminta adalah admin. Riwayat, CSV, dan feedback memerlukan email terverifikasi. Administrator juga dapat mengelola data melalui panel administrasi.</p>
        <p>Hasil tamu menggunakan izin akses dalam sesi pembuatnya. ID submission saja bukan izin berbagi publik. Menghapus cookie atau kehilangan sesi dapat membuat hasil tamu tidak lagi dapat dibuka, tetapi tidak menghapus data pemeriksaan dari server.</p>
        <p>Cookie sesi mendukung login, CAPTCHA, dan akses hasil tamu. Hasil ekstraksi, klasifikasi, terjemahan, atau penjelasan juga dapat disimpan dalam cache untuk mengurangi pemrosesan berulang. Log dan catatan penggunaan mendukung pemantauan serta pembatasan layanan.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="privacy-retention-title">
        <h2 id="privacy-retention-title">Retensi media dan data lain</h2>
        <p>Dengan konfigurasi saat ini, file media dari pemeriksaan yang selesai atau gagal menjadi layak dihapus setelah {{ $mediaRetentionHours }} jam sejak waktu akhir pemrosesan tercatat. File pada pemeriksaan yang masih diproses tidak dibersihkan oleh aturan ini.</p>
        <p>Pembersihan dijadwalkan berkala. Waktu penghapusan aktual bergantung pada scheduler yang berjalan dan keberhasilan penghapusan file; masa retensi bukan jaminan bahwa file pasti hilang tepat pada batas waktu tersebut.</p>
        <p>Aturan ini menghapus file media dan referensi lokasinya, bukan teks, terjemahan, hasil, referensi terkait, atau akun. Data pemeriksaan, termasuk milik tamu, tidak otomatis dihapus oleh pembersihan media. Cache dan log memiliki masa penyimpanan terpisah sesuai konfigurasi masing-masing.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="privacy-deletion-title">
        <h2 id="privacy-deletion-title">Pilihan akun dan penghapusan</h2>
        <p>Setelah masuk, kamu dapat memperbarui nama/email, mengganti kata sandi, atau menghapus akun melalui profil. Penghapusan akun memerlukan kata sandi saat ini dan konfirmasi; tindakan ini menghapus akun, submission milik akun, hasil, referensi terkait, feedback terkait, catatan pemrosesan, dan file media terkait dari penyimpanan aplikasi.</p>
        <p>Penghapusan akun tidak menjalankan pembersihan menyeluruh pada cache, log, atau cadangan. Aplikasi juga tidak mengirim permintaan penghapusan ke layanan eksternal untuk konten yang telah diproses di sana. Data tersebut tidak boleh dianggap sudah terhapus hanya karena akun dihapus.</p>
        <div class="hl-reading-actions">
            @auth
                <x-ui.link :href="route('profile')" variant="secondary">Buka profil</x-ui.link>
            @else
                <x-ui.link :href="route('login')" variant="secondary">Masuk untuk mengelola akun</x-ui.link>
            @endauth
        </div>
    </section>

    <section class="hl-reading-section" aria-labelledby="privacy-updates-title">
        <h2 id="privacy-updates-title">Pembaruan kebijakan</h2>
        <p>Halaman ini mencantumkan tanggal revisi kebijakan. Tinjau kembali isinya untuk memahami praktik pemrosesan data yang dijelaskan saat menggunakan layanan.</p>
        <div class="hl-reading-actions">
            <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
            <x-ui.link :href="route('cara-kerja')" variant="secondary">Cara kerja Hoaxlin</x-ui.link>
        </div>
    </section>
</article>
@endsection
