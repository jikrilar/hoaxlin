@extends('layouts.app')

@section('title', 'Kebijakan Privasi — hoaxlin.id')
@section('description', 'Baca kebijakan privasi hoaxlin.id mengenai pengumpulan, penggunaan, dan perlindungan data pengguna.')

@section('content')
<div style="padding:9rem 0 5rem; min-height:100vh;">
    <div class="container-app" style="max-width:760px;">

        <a href="{{ url('/') }}" class="btn-ghost" style="display:inline-flex; margin-bottom:2rem;" aria-label="Kembali ke beranda">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Kembali
        </a>

        <div class="glass-card" style="padding:3rem;" role="main">
            <h1 style="font-size:2rem; font-weight:800; margin-bottom:0.5rem;">Kebijakan Privasi</h1>
            <p style="color:var(--color-text-muted); font-size:0.9375rem; margin-bottom:2.5rem;">Terakhir diperbarui: 25 Juli 2026</p>

            <div style="display:flex; flex-direction:column; gap:2rem; color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.8;">

                <section aria-labelledby="intro-heading">
                    <h2 id="intro-heading" style="color:var(--color-text-primary); font-size:1.125rem; font-weight:700; margin-bottom:0.75rem;">1. Pendahuluan</h2>
                    <p>hoaxlin.id ("kami", "layanan") berkomitmen untuk melindungi privasi pengguna. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi Anda saat menggunakan sistem deteksi hoax berbasis BERT ini.</p>
                </section>

                <div class="divider"></div>

                <section aria-labelledby="data-heading">
                    <h2 id="data-heading" style="color:var(--color-text-primary); font-size:1.125rem; font-weight:700; margin-bottom:0.75rem;">2. Data yang Dikumpulkan</h2>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:0.625rem;">
                        @foreach([
                            'Teks berita yang Anda kirimkan untuk dianalisis',
                            'Gambar/video yang diunggah (disimpan sementara untuk pemrosesan)',
                            'Tautan URL yang Anda masukkan',
                            'Informasi akun: nama dan email (jika Anda mendaftar)',
                            'Data log teknis: waktu akses, jenis perangkat (tanpa identitas personal)',
                        ] as $item)
                        <li style="display:flex; gap:0.625rem; align-items:flex-start;">
                            <div style="width:5px; height:5px; background:var(--color-primary-light); border-radius:50%; flex-shrink:0; margin-top:0.6rem;" aria-hidden="true"></div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </section>

                <div class="divider"></div>

                <section aria-labelledby="use-heading">
                    <h2 id="use-heading" style="color:var(--color-text-primary); font-size:1.125rem; font-weight:700; margin-bottom:0.75rem;">3. Penggunaan Data</h2>
                    <p style="margin-bottom:0.75rem;">Data yang dikumpulkan digunakan untuk:</p>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:0.625rem;">
                        @foreach([
                            'Menjalankan proses deteksi hoax menggunakan model BERT',
                            'Menyimpan riwayat pengecekan untuk pengguna terdaftar',
                            'Meningkatkan akurasi model melalui umpan balik pengguna (opsional)',
                            'Memantau performa dan keandalan sistem',
                        ] as $item)
                        <li style="display:flex; gap:0.625rem; align-items:flex-start;">
                            <div style="width:5px; height:5px; background:var(--color-primary-light); border-radius:50%; flex-shrink:0; margin-top:0.6rem;" aria-hidden="true"></div>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </section>

                <div class="divider"></div>

                <section aria-labelledby="third-party-heading">
                    <h2 id="third-party-heading" style="color:var(--color-text-primary); font-size:1.125rem; font-weight:700; margin-bottom:0.75rem;">4. Layanan Pihak Ketiga</h2>
                    <p>Sistem kami menggunakan layanan OpenAI API untuk menerjemahkan berita berbahasa Inggris ke Bahasa Indonesia, OCR gambar, transkripsi video, dan penyusunan narasi penjelasan. Konten yang memerlukan fungsi tersebut mungkin diproses oleh layanan ini sesuai dengan <a href="https://openai.com/policies/privacy-policy" target="_blank" rel="noopener noreferrer" style="color:var(--color-primary-light); text-decoration:none;">Kebijakan Privasi OpenAI</a>.</p>
                </section>

                <div class="divider"></div>

                <section aria-labelledby="retention-heading">
                    <h2 id="retention-heading" style="color:var(--color-text-primary); font-size:1.125rem; font-weight:700; margin-bottom:0.75rem;">5. Retensi Data</h2>
                    <p>File media (gambar/video) yang diunggah akan dihapus secara otomatis setelah proses analisis selesai atau paling lambat 24 jam setelah unggahan. Data teks dan hasil analisis disimpan selama akun aktif atau hingga pengguna meminta penghapusan.</p>
                </section>

                <div class="divider"></div>

                <section aria-labelledby="rights-heading">
                    <h2 id="rights-heading" style="color:var(--color-text-primary); font-size:1.125rem; font-weight:700; margin-bottom:0.75rem;">6. Hak Pengguna</h2>
                    <p>Pengguna terdaftar berhak untuk meminta penghapusan seluruh data riwayat melalui pengaturan akun. Untuk pertanyaan lebih lanjut mengenai privasi, hubungi kami melalui form di halaman Tentang.</p>
                </section>

                <div class="warning-box" role="note">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>Kebijakan ini dapat berubah sewaktu-waktu. Perubahan material akan diberitahukan melalui email untuk pengguna terdaftar atau melalui pengumuman di website.</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
