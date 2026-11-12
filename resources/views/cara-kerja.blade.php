@extends('layouts.app')

@section('title', 'Cara kerja Hoaxlin — hoaxlin.id')
@section('description', 'Kenali alur pemeriksaan informasi, prediksi model, dan rujukan terkait di Hoaxlin. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
@php
    $inputOptions = \App\Support\SubmissionFormOptions::from(app(\App\Services\Media\TranscriptionMediaContract::class));
@endphp
<article class="container-app hl-reading-page" aria-labelledby="how-title">
    <header class="hl-reading-intro">
        <h1 id="how-title" class="hl-heading-editorial">Cara kerja Hoaxlin</h1>
        <p>Informasi yang Anda kirim diolah menjadi teks, dianalisis oleh IndoBERT, lalu dilengkapi referensi terkait dan penjelasan bila tersedia.</p>
        <x-ui.disclaimer variant="full" class="hl-reading-note" />
    </header>

    <section class="hl-reading-section" aria-labelledby="input-title">
        <h2 id="input-title">Pilih input yang sesuai</h2>
        <p>Tamu hanya dapat mengirim teks. Pengguna yang sudah masuk ke akun juga dapat menggunakan tautan artikel, gambar, video unggahan, dan tautan video.</p>
        <dl class="hl-reading-definitions">
            <div>
                <dt>Teks</dt>
                <dd>50–50.000 karakter. Kirim isi informasi yang ingin diperiksa, dengan konteks yang cukup untuk dibaca.</dd>
            </div>
            <div>
                <dt>Tautan artikel</dt>
                <dd>URL HTTP atau HTTPS menuju artikel yang dapat diakses. Sistem mengambil dan mengekstrak isi halaman; tidak semua halaman dapat dibaca.</dd>
            </div>
            <div>
                <dt>Gambar</dt>
                <dd>{{ strtoupper(implode(', ', $inputOptions['image']['extensions'])) }}; maksimal {{ $inputOptions['image']['maxSizeLabel'] }} dan {{ $inputOptions['image']['maxWidth'] }} × {{ $inputOptions['image']['maxHeight'] }} piksel. Tulisan pada gambar dibaca melalui pembacaan teks gambar.</dd>
            </div>
            <div>
                <dt>Video unggahan</dt>
                <dd>{{ strtoupper(implode(', ', $inputOptions['video']['extensions'])) }}; maksimal {{ $inputOptions['video']['maxSizeLabel'] }} dan {{ $inputOptions['video']['maxDurationSeconds'] }} detik. Ucapan pada audio diubah menjadi teks.</dd>
            </div>
            <div>
                <dt>Tautan video</dt>
                <dd>URL HTTP atau HTTPS langsung ke file audio/video {{ strtoupper(implode(', ', $inputOptions['videoUrl']['extensions'])) }} yang didukung, dengan batas ukuran dan durasi transkripsi yang sama. Halaman YouTube, TikTok, Instagram, Facebook, atau halaman pemutar tidak didukung.</dd>
            </div>
        </dl>
        <p class="hl-reading-note">Format dan isi file tetap divalidasi saat dikirim. Batas serta pilihan input juga tersedia pada form pemeriksaan.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="pipeline-title">
        <h2 id="pipeline-title">Dari input hingga hasil</h2>
        <p>Proses mengikuti jenis input. Tidak semua input memerlukan ekstraksi media atau terjemahan.</p>
        <ol class="hl-reading-steps">
            <li>
                <h3>Membaca teks dari input</h3>
                <p>Teks dirapikan, isi artikel diambil, gambar dibaca melalui <x-ui.term key="ocr">pembacaan teks gambar</x-ui.term>, atau audio pada media diubah menjadi teks. Kualitas gambar, suara, dan konteks input memengaruhi teks yang dapat dianalisis.</p>
            </li>
            <li>
                <h3>Terjemahan bila diperlukan</h3>
                <p>Bahasa teks diperiksa. Teks Indonesia langsung digunakan; teks Inggris diterjemahkan ke Indonesia sebelum analisis. Input asli, teks hasil ekstraksi, dan terjemahan bila ada dapat ditinjau pada detail hasil.</p>
            </li>
            <li>
                <h3>Analisis pola bahasa</h3>
                <p>IndoBERT menganalisis pola bahasa untuk menghasilkan label prediksi dan <x-ui.term key="confidence">skor keyakinan</x-ui.term>. Model dilatih dengan kelas valid dan hoaks. Status <x-ui.term key="abstention">belum terverifikasi</x-ui.term> diberikan saat skor berada di bawah batas yang ditetapkan layanan model; ini bukan kelas training ketiga.</p>
            </li>
            <li>
                <h3>Pencarian rujukan terkait</h3>
                <p><x-ui.term key="retrieval">Pencarian rujukan</x-ui.term> mencari teks relevan di basis pengetahuan lokal Hoaxlin. Pencarian ini tidak mencakup seluruh internet dan tidak mengubah label atau skor keyakinan model. Referensi terkait tidak otomatis mendukung prediksi model.</p>
            </li>
            <li>
                <h3>Penjelasan bila tersedia</h3>
                <p>OpenAI menyusun penjelasan berdasarkan prediksi, cuplikan input, dan referensi yang tersedia. Penjelasan bukan penentu label kedua. Jika layanan penjelasan tidak tersedia, statusnya ditampilkan secara jujur dan hasil klasifikasi yang sudah selesai tetap dapat dibaca.</p>
            </li>
        </ol>
        <p class="hl-reading-note">Hasil final ditampilkan setelah pemrosesan selesai. Jika ekstraksi, terjemahan yang diperlukan, atau klasifikasi gagal, halaman hasil menampilkan kegagalan dan langkah pemulihan yang tersedia.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="meaning-title">
        <h2 id="meaning-title">Membaca hasil dengan hati-hati</h2>
        <dl class="hl-reading-definitions">
            @foreach([
                'valid' => 'Model memprediksi pola teks sebagai valid. Prediksi ini bukan bukti bahwa seluruh isi informasi benar.',
                'hoax' => 'Model memprediksi pola teks sebagai hoaks. Prediksi ini tetap perlu diperiksa terhadap sumber dan konteks aslinya.',
                'meragukan' => 'Ini bukan bukti bahwa informasi benar atau palsu. Model menahan keputusan karena skor keyakinannya belum mencapai batas keyakinan layanan model; ini bukan klaim bahwa semua sumber telah diperiksa.',
            ] as $label => $meaning)
                <div>
                    <dt>{{ \App\View\Presenters\DetectionPresentation::label($label)['text'] }}</dt>
                    <dd>{{ $meaning }}</dd>
                </div>
            @endforeach
        </dl>
        <h3>Skor keyakinan model</h3>
        <p>Skor keyakinan menunjukkan keyakinan model pada prediksinya, bukan persentase kebenaran berita, akurasi produk, atau kredibilitas sumber. <x-ui.term key="similarity_score">Skor kemiripan</x-ui.term> pada pencarian rujukan mengukur relevansi referensi terhadap teks, bukan tingkat keyakinan model.</p>
        <h3>Referensi dan penjelasan yang belum tersedia</h3>
        <p>Jika tidak ditemukan referensi yang cukup relevan pada basis pengetahuan saat ini, halaman hasil menyatakan keterbatasan tersebut. Ini tidak berarti tidak ada bukti di seluruh internet. Penjelasan dapat sedang disiapkan atau tidak tersedia; sistem tidak membuat referensi atau narasi pengganti yang seolah nyata.</p>
    </section>

    <section class="hl-reading-section" aria-labelledby="review-title">
        <h2 id="review-title">Tinjau sumber secara mandiri</h2>
        <p>Model dapat keliru, termasuk pada informasi baru atau teks yang konteksnya tidak lengkap. Periksa sumber asli, tanggal, dan konteks kutipan, lalu bandingkan dengan sumber terpercaya. Jangan mengambil keputusan hanya dari label atau skor model.</p>
        <div class="hl-reading-actions">
            <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
            <x-ui.link :href="route('tentang')" variant="secondary">Tentang Hoaxlin</x-ui.link>
        </div>
    </section>
</article>
@endsection
