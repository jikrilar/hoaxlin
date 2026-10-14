@extends('layouts.app')

@section('title', 'Cara kerja Hoaxlin — hoaxlin.id')
@section('description', 'Kenali alur input, prediksi IndoBERT, dan referensi terkait dari basis pengetahuan lokal. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
@php
    $inputOptions = \App\Support\SubmissionFormOptions::from(app(\App\Services\Media\TranscriptionMediaContract::class));
@endphp
<article class="container-app hl-reading-page" aria-labelledby="how-title">
    <header class="hl-reading-intro">
        <h1 id="how-title" class="hl-heading-editorial">Cara kerja Hoaxlin</h1>
        <p>Informasi yang kamu kirim diolah menjadi teks, dianalisis oleh IndoBERT, lalu dilengkapi referensi terkait dan penjelasan bila tersedia.</p>
        <p class="hl-reading-note">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
    </header>

    <section class="hl-reading-section" aria-labelledby="input-title">
        <h2 id="input-title">Pilih input yang sesuai</h2>
        <p>Tamu hanya dapat mengirim teks. Setelah masuk ke akun, kamu juga dapat menggunakan tautan artikel, gambar, video unggahan, dan tautan video.</p>
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
                <dd>{{ strtoupper(implode(', ', $inputOptions['image']['extensions'])) }}; maksimal {{ $inputOptions['image']['maxSizeLabel'] }} dan {{ $inputOptions['image']['maxWidth'] }} × {{ $inputOptions['image']['maxHeight'] }} piksel. Teks dibaca melalui OCR.</dd>
            </div>
            <div>
                <dt>Video unggahan</dt>
                <dd>{{ strtoupper(implode(', ', $inputOptions['video']['extensions'])) }}; maksimal {{ $inputOptions['video']['maxSizeLabel'] }} dan {{ $inputOptions['video']['maxDurationSeconds'] }} detik. Ucapan pada audio ditranskripsikan menjadi teks.</dd>
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
                <h3>Ekstraksi teks</h3>
                <p>Teks dirapikan, isi artikel diekstrak, gambar dibaca melalui OCR, atau audio pada media ditranskripsikan. Kualitas gambar, suara, dan konteks input memengaruhi teks yang dapat dianalisis.</p>
            </li>
            <li>
                <h3>Terjemahan bila diperlukan</h3>
                <p>Bahasa teks diperiksa. Teks Indonesia langsung digunakan; teks Inggris diterjemahkan ke Indonesia sebelum klasifikasi. Input asli, teks hasil ekstraksi, dan terjemahan bila ada dapat ditinjau pada detail hasil.</p>
            </li>
            <li>
                <h3>Klasifikasi IndoBERT</h3>
                <p>IndoBERT menganalisis pola bahasa untuk menghasilkan label prediksi dan skor keyakinan model. Model dilatih dengan kelas valid dan hoax; label meragukan adalah abstention saat skor keyakinan di bawah ambang runtime, bukan kelas training ketiga.</p>
            </li>
            <li>
                <h3>Retrieval referensi terkait</h3>
                <p>RAG mencari teks yang relevan dalam basis pengetahuan lokal. Pencarian ini tidak mencakup seluruh web dan tidak mengubah label atau skor keyakinan classifier. Referensi terkait tidak otomatis mendukung prediksi model.</p>
            </li>
            <li>
                <h3>Penjelasan bila tersedia</h3>
                <p>OpenAI menyusun penjelasan berdasarkan prediksi, cuplikan input, dan referensi yang tersedia. Penjelasan bukan classifier kedua. Jika layanan penjelasan tidak tersedia, statusnya ditampilkan secara jujur dan hasil klasifikasi yang sudah selesai tetap dapat dibaca.</p>
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
                'meragukan' => 'Ini bukan bukti bahwa informasi benar atau palsu. Model menahan keputusan karena skor keyakinannya tidak memenuhi ambang runtime; ini bukan klaim bahwa semua sumber telah diperiksa.',
            ] as $label => $meaning)
                <div>
                    <dt>{{ \App\View\Presenters\DetectionPresentation::label($label)['text'] }}</dt>
                    <dd>{{ $meaning }}</dd>
                </div>
            @endforeach
        </dl>
        <h3>Skor keyakinan model</h3>
        <p>Confidence menunjukkan keyakinan classifier pada prediksinya, bukan persentase kebenaran berita, akurasi produk, atau kredibilitas sumber. Skor kemiripan retrieval mengukur relevansi referensi terhadap teks, bukan confidence classifier.</p>
        <h3>Referensi dan penjelasan yang belum tersedia</h3>
        <p>Jika tidak ditemukan referensi yang cukup relevan pada basis pengetahuan saat ini, halaman hasil menyatakan keterbatasan tersebut. Ini tidak berarti tidak ada bukti di seluruh Internet. Penjelasan dapat sedang disiapkan atau tidak tersedia; sistem tidak membuat referensi atau narasi pengganti yang seolah nyata.</p>
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
