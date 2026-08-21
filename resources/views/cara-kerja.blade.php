@extends('layouts.app')

@section('title', 'Cara Kerja — hoaxlin.id')
@section('description', 'Pelajari bagaimana sistem AI BERT kami mendeteksi berita hoax dari berbagai jenis input.')

@section('content')

<!-- Hero -->
<section style="padding:9rem 0 5rem; text-align:center; position:relative; overflow:hidden;" aria-label="Header halaman cara kerja">
    <div class="glow-orb glow-orb-1" style="opacity:0.5;" aria-hidden="true"></div>

    <div class="container-app" style="position:relative; z-index:1;">
        <div class="hero-badge" style="display:inline-flex; margin-bottom:1.25rem; justify-content:center;">
            <span class="hero-badge-dot" aria-hidden="true"></span>
            Teknologi & Metodologi
        </div>
        <h1 style="font-size:clamp(2rem, 5vw, 3.5rem); font-weight:900; margin-bottom:1rem;">
            Cara Kerja <span class="gradient-text">hoaxlin.id</span>
        </h1>
        <p style="color:var(--color-text-secondary); max-width:560px; margin:0 auto; font-size:1.0625rem; line-height:1.7;">
            Dari input berita hingga hasil deteksi — pelajari setiap langkah dalam pipeline sistem pendeteksi hoax berbasis BERT kami.
        </p>
    </div>
</section>

<!-- Pipeline Overview -->
<section style="padding:3rem 0 5rem;" aria-label="Pipeline sistem">
    <div class="container-app" style="max-width:900px;">

        <!-- Step 1: Input -->
        <div class="glass-card" style="padding:2.5rem; margin-bottom:1.5rem; display:grid; grid-template-columns:80px 1fr; gap:2rem; align-items:start;" role="article">
            <div style="width:5rem; height:5rem; background:linear-gradient(135deg, rgba(99,102,241,0.2), rgba(99,102,241,0.08)); border:1px solid rgba(99,102,241,0.3); border-radius:1.25rem; display:flex; align-items:center; justify-content:center;" aria-hidden="true">
                <span style="font-size:2rem;">📥</span>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                    <span style="color:rgba(99,102,241,0.5); font-family:var(--font-display); font-weight:800; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.08em;" aria-label="Langkah 1">LANGKAH 01</span>
                </div>
                <h2 style="font-size:1.375rem; font-weight:700; margin-bottom:0.75rem;">Penerimaan Input</h2>
                <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.75; margin-bottom:1.25rem;">
                    Sistem menerima berita dalam empat format berbeda. Masing-masing format ditangani secara berbeda sebelum masuk ke tahap analisis BERT.
                </p>
                <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:0.75rem;">
                    @foreach([
                        ['icon'=>'🔤', 'name'=>'Teks Langsung', 'desc'=>'Input paling cepat. Langsung ke tokenisasi.'],
                        ['icon'=>'🖼️', 'name'=>'Gambar / OCR', 'desc'=>'Ekstraksi teks via OpenAI Vision API.'],
                        ['icon'=>'🎬', 'name'=>'Video / Transkripsi', 'desc'=>'Audio diekstrak, lalu di-transkripsi Whisper.'],
                        ['icon'=>'🔗', 'name'=>'Tautan URL', 'desc'=>'Artikel di-scraping, konten diekstrak.'],
                    ] as $inp)
                    <div style="padding:0.875rem; background:rgba(13,17,23,0.6); border:1px solid var(--color-border); border-radius:0.75rem;">
                        <div style="font-size:1.25rem; margin-bottom:0.375rem;" aria-hidden="true">{{ $inp['icon'] }}</div>
                        <div style="font-size:0.875rem; font-weight:600; color:var(--color-text-primary); margin-bottom:0.25rem;">{{ $inp['name'] }}</div>
                        <div style="font-size:0.8125rem; color:var(--color-text-muted);">{{ $inp['desc'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Arrow -->
        <div style="text-align:center; margin:0.25rem 0; color:rgba(99,102,241,0.4); font-size:1.5rem;" aria-hidden="true">↓</div>

        <!-- Step 2: Preprocessing -->
        <div class="glass-card" style="padding:2.5rem; margin-bottom:1.5rem; display:grid; grid-template-columns:80px 1fr; gap:2rem; align-items:start;" role="article">
            <div style="width:5rem; height:5rem; background:linear-gradient(135deg, rgba(6,182,212,0.2), rgba(6,182,212,0.08)); border:1px solid rgba(6,182,212,0.3); border-radius:1.25rem; display:flex; align-items:center; justify-content:center;" aria-hidden="true">
                <span style="font-size:2rem;">🔬</span>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                    <span style="color:rgba(6,182,212,0.6); font-family:var(--font-display); font-weight:800; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.08em;" aria-label="Langkah 2">LANGKAH 02</span>
                </div>
                <h2 style="font-size:1.375rem; font-weight:700; margin-bottom:0.75rem;">Pra-pemrosesan Teks</h2>
                <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.75; margin-bottom:1.25rem;">
                    Sebelum masuk ke model BERT, teks dibersihkan dan dinormalisasi agar analisis lebih akurat.
                </p>
                <div style="display:flex; flex-direction:column; gap:0.625rem;">
                    @foreach([
                        'Penghapusan tag HTML, URL, dan karakter non-standar',
                        'Normalisasi teks — singkatan umum, typo, dan ejaan',
                        'Tokenisasi menggunakan WordPiece tokenizer BERT',
                        'Pemotongan teks bila melebihi panjang maksimum (512 token)',
                    ] as $step)
                    <div style="display:flex; gap:0.75rem; align-items:flex-start;">
                        <div style="width:1.25rem; height:1.25rem; background:rgba(6,182,212,0.15); border:1px solid rgba(6,182,212,0.3); border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px;" aria-hidden="true">
                            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#22d3ee" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span style="color:var(--color-text-secondary); font-size:0.9375rem;">{{ $step }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div style="text-align:center; margin:0.25rem 0; color:rgba(99,102,241,0.4); font-size:1.5rem;" aria-hidden="true">↓</div>

        <!-- Step 3: BERT Classification -->
        <div class="glass-card" style="padding:2.5rem; margin-bottom:1.5rem; display:grid; grid-template-columns:80px 1fr; gap:2rem; align-items:start; border-color:rgba(168,85,247,0.25);" role="article">
            <div style="width:5rem; height:5rem; background:linear-gradient(135deg, rgba(168,85,247,0.2), rgba(168,85,247,0.08)); border:1px solid rgba(168,85,247,0.3); border-radius:1.25rem; display:flex; align-items:center; justify-content:center;" aria-hidden="true">
                <span style="font-size:2rem;">🧠</span>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                    <span style="color:rgba(168,85,247,0.7); font-family:var(--font-display); font-weight:800; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.08em;" aria-label="Langkah 3 inti">LANGKAH 03 — INTI</span>
                </div>
                <h2 style="font-size:1.375rem; font-weight:700; margin-bottom:0.75rem;">Klasifikasi BERT</h2>
                <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.75; margin-bottom:1.25rem;">
                    Ini adalah inti dari sistem. Model <strong style="color:var(--color-text-primary);">IndoBERT</strong> yang telah di-fine-tune pada dataset hoax Indonesia menganalisis teks secara mendalam dan mengklasifikasikannya.
                </p>

                <!-- BERT Features -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.25rem;">
                    @foreach([
                        ['title'=>'Bidireksional', 'desc'=>'Membaca konteks dari kiri-kanan DAN kanan-kiri secara bersamaan.', 'color'=>'#c084fc'],
                        ['title'=>'Pretrained', 'desc'=>'Sudah memahami bahasa Indonesia dari corpus besar sebelum fine-tuning.', 'color'=>'#a78bfa'],
                        ['title'=>'Fine-tuned', 'desc'=>'Dilatih ulang pada dataset berita hoax Indonesia berlabel.', 'color'=>'#818cf8'],
                        ['title'=>'Transformer', 'desc'=>'Mekanisme attention 12 layer untuk menangkap relasi antar kata.', 'color'=>'#60a5fa'],
                    ] as $feat)
                    <div style="padding:1rem; background:rgba(168,85,247,0.06); border:1px solid rgba(168,85,247,0.15); border-radius:0.75rem;">
                        <div style="color:{{ $feat['color'] }}; font-weight:600; font-size:0.875rem; margin-bottom:0.375rem;">{{ $feat['title'] }}</div>
                        <div style="color:var(--color-text-muted); font-size:0.8125rem; line-height:1.5;">{{ $feat['desc'] }}</div>
                    </div>
                    @endforeach
                </div>

                <!-- Output Classes -->
                <div style="padding:1.25rem; background:rgba(13,17,23,0.8); border:1px solid var(--color-border); border-radius:0.875rem;">
                    <p style="color:var(--color-text-muted); font-size:0.8125rem; margin-bottom:0.875rem; text-transform:uppercase; letter-spacing:0.06em;">Label Output</p>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <div class="result-badge valid">✅ Valid</div>
                        <div class="result-badge hoax">🚨 Hoax</div>
                        <div class="result-badge meragukan">⚠️ Meragukan</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align:center; margin:0.25rem 0; color:rgba(99,102,241,0.4); font-size:1.5rem;" aria-hidden="true">↓</div>

        <!-- Step 4: Result -->
        <div class="glass-card" style="padding:2.5rem; display:grid; grid-template-columns:80px 1fr; gap:2rem; align-items:start;" role="article">
            <div style="width:5rem; height:5rem; background:linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.08)); border:1px solid rgba(16,185,129,0.3); border-radius:1.25rem; display:flex; align-items:center; justify-content:center;" aria-hidden="true">
                <span style="font-size:2rem;">📊</span>
            </div>
            <div>
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:0.75rem;">
                    <span style="color:rgba(16,185,129,0.7); font-family:var(--font-display); font-weight:800; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.08em;" aria-label="Langkah 4">LANGKAH 04</span>
                </div>
                <h2 style="font-size:1.375rem; font-weight:700; margin-bottom:0.75rem;">Hasil & Penjelasan</h2>
                <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.75; margin-bottom:1.25rem;">
                    Output BERT (label + confidence score) dikirim ke OpenAI API untuk disusun menjadi penjelasan naratif yang mudah dipahami masyarakat umum.
                </p>
                <div style="display:flex; flex-direction:column; gap:0.625rem;">
                    @foreach([
                        ['icon'=>'🏷️', 'text'=>'Label klasifikasi: Valid, Hoax, atau Meragukan'],
                        ['icon'=>'📈', 'text'=>'Confidence score — seberapa yakin model terhadap prediksinya'],
                        ['icon'=>'🤖', 'text'=>'Narasi penjelasan dalam bahasa Indonesia yang mudah dipahami'],
                        ['icon'=>'💾', 'text'=>'Riwayat tersimpan untuk pengguna yang sudah login'],
                    ] as $out)
                    <div style="display:flex; gap:0.75rem; align-items:center; padding:0.625rem 0.875rem; background:rgba(16,185,129,0.05); border:1px solid rgba(16,185,129,0.12); border-radius:0.625rem;">
                        <span aria-hidden="true">{{ $out['icon'] }}</span>
                        <span style="color:var(--color-text-secondary); font-size:0.9375rem;">{{ $out['text'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</section>

<!-- CTA -->
<section style="padding:4rem 0 6rem; text-align:center;" aria-label="Call to action">
    <div class="container-app" style="max-width:600px;">
        <h2 style="font-size:2rem; font-weight:800; margin-bottom:1rem;">
            Siap <span class="gradient-text">Mencoba</span>?
        </h2>
        <p style="color:var(--color-text-secondary); margin-bottom:2rem; font-size:1.0625rem;">
            Coba analisis berita sekarang — gratis, tanpa perlu daftar!
        </p>
        <a href="{{ url('/') }}#cek-berita" class="btn-primary" style="font-size:1rem; padding:0.875rem 2.5rem;">
            <span>Mulai Sekarang</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
    </div>
</section>

@endsection
