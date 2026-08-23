@extends('layouts.app')

@section('title', 'hoaxlin.id — Deteksi Berita Hoax dengan AI BERT')
@section('description', 'Periksa kebenaran berita dengan teknologi AI BERT. Deteksi hoax dari teks, gambar, video, atau tautan berita secara instan dan akurat.')

@php
  // Simple math CAPTCHA for C17 — stored in session, validated in StoreSubmissionRequest
  $captchaA = random_int(1, 9);
  $captchaB = random_int(1, 9);
  session(['captcha_answer' => $captchaA + $captchaB]);
  $captchaQuestion = "$captchaA + $captchaB = ?";
@endphp

@section('content')

<!-- ═══════════════════════════════════════════════
     HERO SECTION
══════════════════════════════════════════════════ -->
<section id="hero" style="position:relative; overflow:hidden; padding: 9rem 0 5rem;" aria-label="Hero section">
    <!-- Glow Orbs -->
    <div class="glow-orb glow-orb-1" aria-hidden="true"></div>
    <div class="glow-orb glow-orb-2" aria-hidden="true"></div>

    <!-- Animated grid background -->
    <div aria-hidden="true" style="position:absolute; inset:0; background-image: linear-gradient(rgba(99,102,241,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.04) 1px, transparent 1px); background-size:60px 60px; pointer-events:none;"></div>

    <div class="container-app" style="position:relative; z-index:1; text-align:center;">

        <!-- Badge -->
        <div class="animate-fade-in-up" style="display:flex; justify-content:center; margin-bottom:2rem;">
            <div class="hero-badge" role="status">
                <span class="hero-badge-dot" aria-hidden="true"></span>
                Didukung Model BERT Bahasa Indonesia
            </div>
        </div>

        <!-- Headline -->
        <h1 class="animate-fade-in-up delay-100" style="font-size:clamp(2.5rem, 6vw, 4.5rem); font-weight:900; margin-bottom:1.5rem; letter-spacing:-0.02em; line-height:1.1;">
            Perangi Hoax dengan<br>
            <span class="gradient-text">Kecerdasan Buatan</span>
        </h1>

        <!-- Subtitle -->
        <p class="animate-fade-in-up delay-200" style="font-size:clamp(1rem, 2vw, 1.25rem); color:var(--color-text-secondary); max-width:640px; margin:0 auto 2.5rem; line-height:1.7;">
            Unggah berita yang kamu terima — berupa teks, foto, video, atau tautan — dan sistem kami akan menganalisisnya menggunakan model BERT yang terlatih khusus untuk bahasa Indonesia.
        </p>

        <!-- CTAs -->
        <div class="animate-fade-in-up delay-300" style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; margin-bottom:4rem;">
            <a href="#cek-berita" class="btn-primary" style="font-size:1rem; padding:0.875rem 2rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <span>Cek Berita Sekarang</span>
            </a>
            <a href="#cara-kerja" class="btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Pelajari Cara Kerjanya
            </a>
        </div>

        <!-- Stats Row -->
        <div class="animate-fade-in-up delay-400" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; max-width:600px; margin:0 auto;" role="region" aria-label="Statistik sistem">
            <div class="stat-card">
                <div class="stat-value" aria-label="Lebih dari 95 persen akurasi">95%+</div>
                <div class="stat-label">Akurasi Model</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" aria-label="Kurang dari 15 detik proses">15s</div>
                <div class="stat-label">Waktu Proses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" aria-label="4 jenis input">4</div>
                <div class="stat-label">Jenis Input</div>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════════════
     CEK BERITA (MAIN FORM)
══════════════════════════════════════════════════ -->
<section id="cek-berita" style="padding:4rem 0 6rem;" aria-label="Form cek berita">
    <div class="container-app" style="max-width:860px;">

        <div class="glass-card" style="padding:2.5rem;" role="main">

            <!-- Form Header -->
            <div style="margin-bottom:2rem;">
                <h2 style="font-size:1.625rem; font-weight:700; margin-bottom:0.5rem;">
                    Cek Kebenaran Berita
                </h2>
                <p style="color:var(--color-text-muted); font-size:0.9375rem;">
                    Pilih jenis input, lalu masukkan konten berita yang ingin kamu verifikasi.
                </p>
            </div>

            <!-- Input Type Tabs -->
            <div class="input-tabs" role="tablist" aria-label="Jenis input berita" style="margin-bottom:1.75rem;">
                <button id="tab-teks" class="input-tab active" role="tab" aria-selected="true" aria-controls="panel-teks" onclick="switchTab('teks')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Teks
                </button>
                <button id="tab-gambar" class="input-tab" role="tab" aria-selected="false" aria-controls="panel-gambar" onclick="switchTab('gambar')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    Gambar
                </button>
                <button id="tab-video" class="input-tab" role="tab" aria-selected="false" aria-controls="panel-video" onclick="switchTab('video')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                    Video
                </button>
                <button id="tab-url" class="input-tab" role="tab" aria-selected="false" aria-controls="panel-url" onclick="switchTab('url')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                    Tautan URL
                </button>
            </div>

            <!-- ── Panel: Teks ── -->
            <div id="panel-teks" class="tab-content active" role="tabpanel" aria-labelledby="tab-teks">
                <form id="form-teks" action="{{ url('/deteksi') }}" method="POST" onsubmit="submitForm(event, 'teks')">
                    @csrf
                    <input type="hidden" name="input_type" value="text">

                    <label for="teks-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">
                        Teks Berita
                    </label>
                    <textarea
                        id="teks-input"
                        name="raw_input"
                        class="form-textarea"
                        style="height:220px;"
                        placeholder="Tempel atau ketik teks berita yang ingin kamu verifikasi di sini...

Contoh: 'Pemerintah mengumumkan kebijakan baru terkait...' "
                        required
                        minlength="50"
                        aria-describedby="teks-hint"
                    ></textarea>

                    <div id="teks-hint" style="display:flex; justify-content:space-between; align-items:center; margin-top:0.5rem;">
                        <span style="color:var(--color-text-muted); font-size:0.8125rem;">Minimal 50 karakter untuk analisis yang akurat</span>
                        <span id="teks-count" style="color:var(--color-text-muted); font-size:0.8125rem;" aria-live="polite">0 karakter</span>
                    </div>

                    <!-- Honeypot + CAPTCHA (C17) -->
                    <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div style="display:grid; grid-template-columns:1fr auto; gap:0.75rem; align-items:end; margin-top:1rem; padding:0.875rem; background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.12); border-radius:0.75rem;">
                        <div>
                            <label for="captcha-teks" style="display:block; color:var(--color-text-secondary); font-size:0.8125rem; font-weight:500; margin-bottom:0.375rem;">Keamanan: Berapa {{ $captchaQuestion }} <span style="color:#f87171;">*</span></label>
                            <input type="number" id="captcha-teks" name="captcha_answer" class="form-input" style="max-width:140px;" placeholder="?" required inputmode="numeric" autocomplete="off">
                        </div>
                        <span style="color:var(--color-text-muted); font-size:0.75rem; padding-bottom:0.625rem;">Batas: 30/hari (IP), 100/hari (akun)</span>
                    </div>

                    <div style="margin-top:1.5rem;">
                        <button type="submit" class="btn-primary" id="submit-teks" style="width:100%;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            <span>Analisis Sekarang</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── Panel: Gambar ── -->
            <div id="panel-gambar" class="tab-content" role="tabpanel" aria-labelledby="tab-gambar">
                <form id="form-gambar" action="{{ url('/deteksi') }}" method="POST" enctype="multipart/form-data" onsubmit="submitForm(event, 'gambar')">
                    @csrf
                    <input type="hidden" name="input_type" value="image">

                    <label for="gambar-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">
                        Unggah Foto / Tangkapan Layar Berita
                    </label>

                    <div class="upload-zone" id="gambar-zone" onclick="document.getElementById('gambar-input').click()" role="button" tabindex="0" aria-label="Klik atau seret gambar ke sini"
                         onkeydown="if(event.key==='Enter'||event.key===' ') document.getElementById('gambar-input').click()"
                         ondragover="event.preventDefault(); this.classList.add('dragover')"
                         ondragleave="this.classList.remove('dragover')"
                         ondrop="handleFileDrop(event, 'gambar')">
                        <input type="file" id="gambar-input" name="media_file" accept="image/*" onchange="previewFile(event, 'gambar')" required>
                        <div class="upload-zone-icon" aria-hidden="true">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </div>
                        <div id="gambar-preview-label">
                            <div class="upload-zone-title">Klik atau Seret Gambar ke Sini</div>
                            <div class="upload-zone-subtitle">PNG, JPG, WEBP, HEIC hingga 10MB</div>
                        </div>
                        <img id="gambar-preview-img" src="" alt="" style="display:none; max-height:200px; border-radius:0.5rem; object-fit:contain;">
                    </div>

                    <div class="info-box" style="margin-top:1rem;" role="note">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Teks akan diekstraksi otomatis dari gambar menggunakan teknologi OCR (Optical Character Recognition) berbasis AI, lalu dianalisis oleh model BERT.</span>
                    </div>

                    <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div style="display:grid; grid-template-columns:1fr auto; gap:0.75rem; align-items:end; margin-top:1rem; padding:0.875rem; background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.12); border-radius:0.75rem;">
                        <div>
                            <label for="captcha-gambar" style="display:block; color:var(--color-text-secondary); font-size:0.8125rem; font-weight:500; margin-bottom:0.375rem;">Keamanan: Berapa {{ $captchaQuestion }} <span style="color:#f87171;">*</span></label>
                            <input type="number" id="captcha-gambar" name="captcha_answer" class="form-input" style="max-width:140px;" placeholder="?" required inputmode="numeric" autocomplete="off">
                        </div>
                        <span style="color:var(--color-text-muted); font-size:0.75rem; padding-bottom:0.625rem;">Batas: 30/hari (IP)</span>
                    </div>

                    <div style="margin-top:1.5rem;">
                        <button type="submit" class="btn-primary" id="submit-gambar" style="width:100%;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            <span>Analisis Gambar</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── Panel: Video ── -->
            <div id="panel-video" class="tab-content" role="tabpanel" aria-labelledby="tab-video">
                <form id="form-video" action="{{ url('/deteksi') }}" method="POST" enctype="multipart/form-data" onsubmit="submitForm(event, 'video')">
                    @csrf
                    <input type="hidden" name="input_type" id="video-input-type" value="video">

                    <!-- Video sub-tabs -->
                    <div style="display:flex; gap:0.5rem; margin-bottom:1.25rem;" role="group" aria-label="Jenis input video">
                        <button type="button" id="video-tab-upload" onclick="switchVideoTab('upload')"
                            style="padding:0.5rem 1rem; border-radius:0.5rem; border:1px solid rgba(99,102,241,0.3); background:rgba(99,102,241,0.15); color:var(--color-primary-light); font-size:0.875rem; font-weight:500; cursor:pointer; transition:all 0.2s;"
                            aria-pressed="true">
                            Unggah File Video
                        </button>
                        <button type="button" id="video-tab-url" onclick="switchVideoTab('url')"
                            style="padding:0.5rem 1rem; border-radius:0.5rem; border:1px solid var(--color-border); background:transparent; color:var(--color-text-muted); font-size:0.875rem; font-weight:500; cursor:pointer; transition:all 0.2s;"
                            aria-pressed="false">
                            Tautan Video
                        </button>
                    </div>

                    <!-- Upload Video -->
                    <div id="video-upload-panel">
                        <label for="video-file-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">File Video</label>
                        <div class="upload-zone" onclick="document.getElementById('video-file-input').click()" role="button" tabindex="0" aria-label="Klik atau seret file video ke sini"
                             ondragover="event.preventDefault(); this.classList.add('dragover')"
                             ondragleave="this.classList.remove('dragover')"
                             ondrop="handleFileDrop(event, 'video')">
                            <input type="file" id="video-file-input" name="media_file" accept="video/*" onchange="previewFile(event, 'video')">
                            <div class="upload-zone-icon" aria-hidden="true">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                            </div>
                            <div id="video-preview-label">
                                <div class="upload-zone-title">Klik atau Seret Video ke Sini</div>
                                <div class="upload-zone-subtitle">MP4, MOV, AVI hingga 200MB</div>
                            </div>
                        </div>
                    </div>

                    <!-- URL Video -->
                    <div id="video-url-panel" style="display:none;">
                        <label for="video-url-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">Tautan Video (YouTube, dll.)</label>
                        <input type="url" id="video-url-input" name="source_url" class="form-input"
                               placeholder="https://www.youtube.com/watch?v=..."
                               aria-label="URL video berita">
                    </div>

                    <div class="warning-box" style="margin-top:1rem;" role="note">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Proses video memerlukan waktu lebih lama (ekstraksi audio + transkripsi). Kamu akan menerima notifikasi saat hasil siap. Disarankan masuk ke akun untuk melihat riwayat.</span>
                    </div>

                    <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div style="display:grid; grid-template-columns:1fr auto; gap:0.75rem; align-items:end; margin-top:1rem; padding:0.875rem; background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.12); border-radius:0.75rem;">
                        <div>
                            <label for="captcha-video" style="display:block; color:var(--color-text-secondary); font-size:0.8125rem; font-weight:500; margin-bottom:0.375rem;">Keamanan: Berapa {{ $captchaQuestion }} <span style="color:#f87171;">*</span></label>
                            <input type="number" id="captcha-video" name="captcha_answer" class="form-input" style="max-width:140px;" placeholder="?" required inputmode="numeric" autocomplete="off">
                        </div>
                        <span style="color:var(--color-text-muted); font-size:0.75rem; padding-bottom:0.625rem;">Batas: 30/hari (IP)</span>
                    </div>

                    <div style="margin-top:1.5rem;">
                        <button type="submit" class="btn-primary" id="submit-video" style="width:100%;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            <span>Analisis Video</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── Panel: URL ── -->
            <div id="panel-url" class="tab-content" role="tabpanel" aria-labelledby="tab-url">
                <form id="form-url" action="{{ url('/deteksi') }}" method="POST" onsubmit="submitForm(event, 'url')">
                    @csrf
                    <input type="hidden" name="input_type" value="url">

                    <label for="url-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">
                        Tautan Berita
                    </label>
                    <input
                        type="url"
                        id="url-input"
                        name="source_url"
                        class="form-input"
                        placeholder="https://www.detik.com/berita/..."
                        required
                        aria-describedby="url-hint"
                    >
                    <p id="url-hint" style="color:var(--color-text-muted); font-size:0.8125rem; margin-top:0.5rem;">
                        Tempel tautan artikel berita penuh. Sistem akan mengekstrak konten artikelnya secara otomatis.
                    </p>

                    <!-- URL Preview -->
                    <div id="url-preview" style="display:none; margin-top:1rem; padding:1rem; background:rgba(20,28,46,0.8); border:1px solid var(--color-border); border-radius:0.875rem;" aria-live="polite">
                        <div style="display:flex; gap:0.75rem; align-items:flex-start;">
                            <div style="width:2.5rem; height:2.5rem; background:rgba(99,102,241,0.1); border-radius:0.5rem; display:flex; align-items:center; justify-content:center; flex-shrink:0;" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                            </div>
                            <div>
                                <div id="url-domain" style="color:var(--color-primary-light); font-size:0.875rem; font-weight:600;"></div>
                                <div id="url-full" style="color:var(--color-text-muted); font-size:0.8125rem; word-break:break-all; margin-top:0.25rem;"></div>
                            </div>
                        </div>
                    </div>

                    <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div style="display:grid; grid-template-columns:1fr auto; gap:0.75rem; align-items:end; margin-top:1rem; padding:0.875rem; background:rgba(99,102,241,0.06); border:1px solid rgba(99,102,241,0.12); border-radius:0.75rem;">
                        <div>
                            <label for="captcha-url" style="display:block; color:var(--color-text-secondary); font-size:0.8125rem; font-weight:500; margin-bottom:0.375rem;">Keamanan: Berapa {{ $captchaQuestion }} <span style="color:#f87171;">*</span></label>
                            <input type="number" id="captcha-url" name="captcha_answer" class="form-input" style="max-width:140px;" placeholder="?" required inputmode="numeric" autocomplete="off">
                        </div>
                        <span style="color:var(--color-text-muted); font-size:0.75rem; padding-bottom:0.625rem;">Batas: 30/hari (IP)</span>
                    </div>

                    <div style="margin-top:1.5rem;">
                        <button type="submit" class="btn-primary" id="submit-url" style="width:100%;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            <span>Analisis Tautan</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Loading overlay (shown during processing) -->
            <div id="loading-overlay" style="display:none; text-align:center; padding:2.5rem 1rem;" role="status" aria-live="assertive">
                <div style="display:flex; justify-content:center; margin-bottom:1.5rem;">
                    <div class="spinner" aria-label="Memproses..."></div>
                </div>
                <h3 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;">Sedang Menganalisis...</h3>

                <!-- Progress Steps -->
                <div style="max-width:320px; margin:0 auto; text-align:left;" aria-label="Langkah proses">
                    <div class="progress-step active" id="step-1">
                        <div class="step-icon active" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                        </div>
                        <span id="step-1-text" style="font-size:0.875rem;">Memproses input...</span>
                    </div>
                    <div class="progress-step pending" id="step-2">
                        <div class="step-icon pending" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                        </div>
                        <span style="font-size:0.875rem;">Klasifikasi BERT...</span>
                    </div>
                    <div class="progress-step pending" id="step-3">
                        <div class="step-icon pending" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        </div>
                        <span style="font-size:0.875rem;">Menyusun penjelasan...</span>
                    </div>
                </div>

                <p style="color:var(--color-text-muted); font-size:0.8125rem; margin-top:1.5rem;">
                    Mohon tunggu sebentar. Jangan tutup halaman ini.
                </p>
            </div>

            <!-- Disclaimer -->
            <div style="margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--color-border-light);">
                <p style="color:var(--color-text-muted); font-size:0.8125rem; text-align:center; line-height:1.6;">
                    ⚠️ Hasil analisis bersifat <strong style="color:var(--color-text-secondary);">indikatif probabilistik</strong>, bukan keputusan hukum atau jaminan mutlak.
                    Selalu verifikasi ke sumber terpercaya seperti Kominfo, Mafindo, atau CekFakta.
                </p>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════════════
     HOW IT WORKS
══════════════════════════════════════════════════ -->
<section id="cara-kerja" style="padding:5rem 0;" aria-label="Cara kerja sistem">
    <div class="container-app">

        <div style="text-align:center; margin-bottom:3.5rem;">
            <div class="hero-badge" style="display:inline-flex; margin-bottom:1.25rem;" aria-hidden="true">
                <span class="hero-badge-dot"></span>
                Teknologi Kami
            </div>
            <h2 style="font-size:clamp(1.875rem, 4vw, 2.75rem); font-weight:800; margin-bottom:1rem;">
                Bagaimana <span class="gradient-text">hoaxlin.id</span> Bekerja?
            </h2>
            <p style="color:var(--color-text-secondary); max-width:560px; margin:0 auto; font-size:1.0625rem; line-height:1.7;">
                Pipeline end-to-end dari input berita hingga hasil deteksi yang mudah dipahami
            </p>
        </div>

        <!-- Process Steps -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1.5rem; margin-bottom:4rem;" role="list" aria-label="Langkah-langkah proses">

            @php
            $steps = [
                ['num'=>'01', 'icon'=>'📥', 'title'=>'Input Berita', 'desc'=>'Masukkan berita melalui teks, foto tangkapan layar, video, atau tautan artikel.', 'color'=>'rgba(99,102,241,0.12)'],
                ['num'=>'02', 'icon'=>'🔬', 'title'=>'Ekstraksi Teks', 'desc'=>'Untuk gambar: OCR. Untuk video: transkripsi audio. Untuk URL: scraping artikel secara otomatis.', 'color'=>'rgba(6,182,212,0.12)'],
                ['num'=>'03', 'icon'=>'🧠', 'title'=>'Analisis BERT', 'desc'=>'Model IndoBERT yang di-fine-tune mengklasifikasikan teks ke kelas Valid, Hoax, atau Meragukan.', 'color'=>'rgba(168,85,247,0.12)'],
                ['num'=>'04', 'icon'=>'📊', 'title'=>'Hasil & Penjelasan', 'desc'=>'Ditampilkan label, confidence score, dan narasi penjelasan yang mudah dipahami awam.', 'color'=>'rgba(16,185,129,0.12)'],
            ];
            @endphp

            @foreach($steps as $step)
            <article class="feature-card" style="background:rgba(13,17,23,0.7);" role="listitem">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                    <div class="feature-icon" style="background:{{ $step['color'] }}; font-size:1.625rem;" aria-hidden="true">{{ $step['icon'] }}</div>
                    <span style="color:rgba(99,102,241,0.3); font-family:var(--font-display); font-weight:800; font-size:1.5rem;" aria-hidden="true">{{ $step['num'] }}</span>
                </div>
                <h3 style="font-size:1.0625rem; font-weight:700; margin-bottom:0.625rem;">{{ $step['title'] }}</h3>
                <p style="color:var(--color-text-muted); font-size:0.875rem; line-height:1.65;">{{ $step['desc'] }}</p>
            </article>
            @endforeach
        </div>

        <!-- BERT Explanation Card -->
        <div class="glass-card" style="padding:2.5rem; display:grid; grid-template-columns:1fr 1fr; gap:2.5rem; align-items:center;" role="complementary" aria-label="Tentang BERT">
            <div>
                <div class="hero-badge" style="display:inline-flex; margin-bottom:1.25rem;">
                    <span class="hero-badge-dot" aria-hidden="true"></span>
                    Powered by BERT
                </div>
                <h3 style="font-size:1.5rem; font-weight:700; margin-bottom:1rem;">
                    Mengapa <span class="gradient-text">BERT</span>?
                </h3>
                <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.7; margin-bottom:1.25rem;">
                    BERT (Bidirectional Encoder Representations from Transformers) memahami konteks kata secara dua arah — berbeda dari model lama yang membaca teks dari kiri ke kanan saja.
                </p>
                <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.7; margin-bottom:1.5rem;">
                    Kami menggunakan <strong style="color:var(--color-text-primary);">IndoBERT</strong>, model BERT yang dilatih khusus pada korpus bahasa Indonesia, kemudian di-fine-tune pada dataset berita hoax Indonesia.
                </p>
                <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                    @foreach(['Analisis kontekstual', 'Bahasa Indonesia', 'Fine-tuned', 'Open-source'] as $tag)
                    <span style="padding:0.25rem 0.75rem; background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.2); border-radius:2rem; color:var(--color-primary-light); font-size:0.8125rem; font-weight:500;">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>
            <div>
                <!-- Model Architecture Visual -->
                <div style="background:rgba(13,17,23,0.9); border:1px solid var(--color-border); border-radius:1rem; padding:1.75rem;" aria-label="Visualisasi arsitektur model">
                    <div style="text-align:center; margin-bottom:1.25rem;">
                        <span style="color:var(--color-text-muted); font-size:0.8125rem; text-transform:uppercase; letter-spacing:0.06em;">Arsitektur Model</span>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.625rem;">
                        @php
                        $layers = [
                            ['label'=>'Input Teks Berita', 'bg'=>'rgba(99,102,241,0.12)', 'border'=>'rgba(99,102,241,0.3)', 'color'=>'#818cf8'],
                            ['label'=>'Tokenizer (WordPiece)', 'bg'=>'rgba(99,102,241,0.08)', 'border'=>'rgba(99,102,241,0.2)', 'color'=>'#a5b4fc'],
                            ['label'=>'BERT Encoder (12 layers)', 'bg'=>'rgba(6,182,212,0.12)', 'border'=>'rgba(6,182,212,0.3)', 'color'=>'#22d3ee'],
                            ['label'=>'Classifier Layer', 'bg'=>'rgba(168,85,247,0.12)', 'border'=>'rgba(168,85,247,0.25)', 'color'=>'#c084fc'],
                            ['label'=>'Valid / Hoax / Meragukan', 'bg'=>'rgba(16,185,129,0.12)', 'border'=>'rgba(16,185,129,0.3)', 'color'=>'#34d399'],
                        ];
                        @endphp
                        @foreach($layers as $i => $layer)
                        <div style="padding:0.625rem 1rem; background:{{ $layer['bg'] }}; border:1px solid {{ $layer['border'] }}; border-radius:0.5rem; display:flex; align-items:center; gap:0.625rem;" role="listitem">
                            <div style="width:6px; height:6px; border-radius:50%; background:{{ $layer['color'] }}; flex-shrink:0;" aria-hidden="true"></div>
                            <span style="color:{{ $layer['color'] }}; font-size:0.8125rem; font-weight:500;">{{ $layer['label'] }}</span>
                        </div>
                        @if($i < count($layers)-1)
                        <div style="text-align:center; color:rgba(99,102,241,0.4); font-size:0.75rem;" aria-hidden="true">↓</div>
                        @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════════════
     FEATURES
══════════════════════════════════════════════════ -->
<section style="padding:5rem 0; background:linear-gradient(180deg, transparent 0%, rgba(13,17,23,0.5) 100%);" aria-label="Fitur-fitur unggulan">
    <div class="container-app">

        <div style="text-align:center; margin-bottom:3.5rem;">
            <h2 style="font-size:clamp(1.875rem, 4vw, 2.75rem); font-weight:800; margin-bottom:1rem;">
                Fitur <span class="gradient-text">Unggulan</span>
            </h2>
            <p style="color:var(--color-text-secondary); max-width:520px; margin:0 auto; font-size:1.0625rem;">
                Dirancang untuk kemudahan penggunaan sehari-hari oleh masyarakat umum
            </p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem;" role="list">
            @php
            $features = [
                ['icon'=>'🔤', 'color'=>'rgba(99,102,241,0.12)', 'title'=>'Input Teks Langsung', 'desc'=>'Tempel teks berita dan dapatkan hasil analisis dalam hitungan detik. Tidak perlu daftar untuk menggunakannya.'],
                ['icon'=>'🖼️', 'color'=>'rgba(6,182,212,0.12)', 'title'=>'OCR Gambar Otomatis', 'desc'=>'Unggah foto tangkapan layar berita dari WhatsApp atau media sosial. Teks diekstraksi otomatis menggunakan AI.'],
                ['icon'=>'🎬', 'color'=>'rgba(168,85,247,0.12)', 'title'=>'Transkripsi Video', 'desc'=>'Unggah video atau masukkan tautan YouTube. Audio ditranskripsi menjadi teks menggunakan Whisper AI.'],
                ['icon'=>'🔗', 'color'=>'rgba(16,185,129,0.12)', 'title'=>'Analisis Tautan', 'desc'=>'Tempel URL artikel berita. Sistem mengambil dan mengekstraksi konten artikel secara otomatis.'],
                ['icon'=>'📈', 'color'=>'rgba(245,158,11,0.12)', 'title'=>'Skor Keyakinan', 'desc'=>'Setiap hasil dilengkapi confidence score yang menunjukkan seberapa yakin model terhadap prediksinya.'],
                ['icon'=>'📋', 'color'=>'rgba(239,68,68,0.12)', 'title'=>'Riwayat Pengecekan', 'desc'=>'Daftarkan akun gratis untuk menyimpan riwayat pengecekan dan memberikan umpan balik hasil analisis.'],
            ];
            @endphp

            @foreach($features as $f)
            <article class="feature-card" role="listitem">
                <div class="feature-icon" style="background:{{ $f['color'] }};" aria-hidden="true">{{ $f['icon'] }}</div>
                <h3 style="font-size:1.0625rem; font-weight:700; margin-bottom:0.625rem;">{{ $f['title'] }}</h3>
                <p style="color:var(--color-text-muted); font-size:0.875rem; line-height:1.65;">{{ $f['desc'] }}</p>
            </article>
            @endforeach
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════════════
     CTA BANNER
══════════════════════════════════════════════════ -->
<section style="padding:5rem 0;" aria-label="Call to action">
    <div class="container-app" style="max-width:700px; text-align:center;">
        <div class="glass-card" style="padding:3.5rem 2rem; position:relative; overflow:hidden;">
            <div style="position:absolute; inset:0; background:radial-gradient(ellipse 80% 60% at 50% 0%, rgba(99,102,241,0.12) 0%, transparent 60%); pointer-events:none;" aria-hidden="true"></div>
            <div style="position:relative;">
                <div style="font-size:3.5rem; margin-bottom:1rem;" aria-hidden="true">🛡️</div>
                <h2 style="font-size:clamp(1.75rem, 4vw, 2.5rem); font-weight:800; margin-bottom:1rem;">
                    Jangan Sebarkan<br><span class="gradient-text">Sebelum Dicek!</span>
                </h2>
                <p style="color:var(--color-text-secondary); margin-bottom:2rem; font-size:1.0625rem; line-height:1.7; max-width:480px; margin-left:auto; margin-right:auto;">
                    Jadilah bagian dari gerakan literasi digital Indonesia. Cek berita sebelum dibagikan — gratis, cepat, dan akurat.
                </p>
                <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
                    <a href="#cek-berita" class="btn-primary" style="font-size:1rem; padding:0.875rem 2rem;">
                        <span>Mulai Cek Sekarang</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                    @guest
                    <a href="{{ route('register') }}" class="btn-secondary">Daftar Akun Gratis</a>
                    @endguest
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
// ── Tab Switching ──
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.input-tab').forEach(tab => {
        tab.classList.remove('active');
        tab.setAttribute('aria-selected', 'false');
    });
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.add('active');
    activeTab.setAttribute('aria-selected', 'true');

    // Update panels
    document.querySelectorAll('.tab-content').forEach(panel => panel.classList.remove('active'));
    document.getElementById('panel-' + tabName).classList.add('active');

    // Hide loading if visible
    document.getElementById('loading-overlay').style.display = 'none';
}

// ── Video Sub-tabs ──
function switchVideoTab(type) {
    const isUpload = type === 'upload';
    document.getElementById('video-upload-panel').style.display = isUpload ? 'block' : 'none';
    document.getElementById('video-url-panel').style.display = isUpload ? 'none' : 'block';

    const uploadBtn = document.getElementById('video-tab-upload');
    const urlBtn = document.getElementById('video-tab-url');

    uploadBtn.style.background = isUpload ? 'rgba(99,102,241,0.15)' : 'transparent';
    uploadBtn.style.color = isUpload ? 'var(--color-primary-light)' : 'var(--color-text-muted)';
    uploadBtn.style.borderColor = isUpload ? 'rgba(99,102,241,0.3)' : 'var(--color-border)';
    uploadBtn.setAttribute('aria-pressed', isUpload);

    urlBtn.style.background = !isUpload ? 'rgba(99,102,241,0.15)' : 'transparent';
    urlBtn.style.color = !isUpload ? 'var(--color-primary-light)' : 'var(--color-text-muted)';
    urlBtn.style.borderColor = !isUpload ? 'rgba(99,102,241,0.3)' : 'var(--color-border)';
    urlBtn.setAttribute('aria-pressed', !isUpload);

    document.getElementById('video-input-type').value = isUpload ? 'video' : 'video_url';
    if (isUpload) {
        document.getElementById('video-url-input').removeAttribute('required');
        document.getElementById('video-file-input').setAttribute('required', '');
    } else {
        document.getElementById('video-file-input').removeAttribute('required');
        document.getElementById('video-url-input').setAttribute('required', '');
    }
}

// ── Character Counter ──
const tekstInput = document.getElementById('teks-input');
const teksCount = document.getElementById('teks-count');
if (tekstInput) {
    tekstInput.addEventListener('input', () => {
        const len = tekstInput.value.length;
        teksCount.textContent = len + ' karakter';
        teksCount.style.color = len >= 50 ? 'var(--color-success)' : 'var(--color-text-muted)';
    });
}

// ── File Preview ──
function previewFile(event, type) {
    const file = event.target.files[0];
    if (!file) return;
    if (type === 'gambar') {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = document.getElementById('gambar-preview-img');
            const label = document.getElementById('gambar-preview-label');
            img.src = e.target.result;
            img.style.display = 'block';
            label.innerHTML = `<div class="upload-zone-title" style="font-size:0.875rem;">📎 ${file.name}</div><div class="upload-zone-subtitle">${(file.size/1024/1024).toFixed(2)} MB</div>`;
        };
        reader.readAsDataURL(file);
    } else if (type === 'video') {
        const label = document.getElementById('video-preview-label');
        label.innerHTML = `<div class="upload-zone-title">🎬 ${file.name}</div><div class="upload-zone-subtitle">${(file.size/1024/1024).toFixed(2)} MB</div>`;
    }
}

// ── Drag & Drop ──
function handleFileDrop(event, type) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');
    const file = event.dataTransfer.files[0];
    if (!file) return;
    const inputId = type === 'gambar' ? 'gambar-input' : 'video-file-input';
    const input = document.getElementById(inputId);
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    previewFile({ target: input }, type);
}

// ── URL Preview ──
const urlInput = document.getElementById('url-input');
if (urlInput) {
    urlInput.addEventListener('input', () => {
        const val = urlInput.value.trim();
        const preview = document.getElementById('url-preview');
        if (val && val.startsWith('http')) {
            try {
                const url = new URL(val);
                document.getElementById('url-domain').textContent = url.hostname;
                document.getElementById('url-full').textContent = val;
                preview.style.display = 'block';
            } catch { preview.style.display = 'none'; }
        } else {
            preview.style.display = 'none';
        }
    });
}

// ── Form Submission with Loading ──
function submitForm(event, type) {
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const overlay = document.getElementById('loading-overlay');

    // Show loading overlay instead of form
    document.querySelectorAll('.tab-content').forEach(p => p.style.display = 'none');
    overlay.style.display = 'block';

    // Animate progress steps
    const step1Text = document.getElementById('step-1-text');
    const step2 = document.getElementById('step-2');
    const step3 = document.getElementById('step-3');

    const stepTexts = {
        teks: 'Membersihkan & memvalidasi teks...',
        gambar: 'Mengekstraksi teks dari gambar (OCR)...',
        video: 'Mengekstraksi audio & melakukan transkripsi...',
        url: 'Mengambil konten dari tautan...',
    };
    step1Text.textContent = stepTexts[type] || 'Memproses input...';

    setTimeout(() => {
        document.getElementById('step-1').className = 'progress-step done';
        document.getElementById('step-1').querySelector('.step-icon').className = 'step-icon done';
        step2.className = 'progress-step active';
        step2.querySelector('.step-icon').className = 'step-icon active';
    }, 1500);

    setTimeout(() => {
        step2.className = 'progress-step done';
        step2.querySelector('.step-icon').className = 'step-icon done';
        step3.className = 'progress-step active';
        step3.querySelector('.step-icon').className = 'step-icon active';
    }, 3500);

    // Let the form submit naturally
    // event.preventDefault(); // Remove this if you want real submission
}

// ── Smooth scroll for anchor links ──
document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', (e) => {
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ── Intersection Observer for animations ──
const animateEls = document.querySelectorAll('.feature-card, .stat-card');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.1 });
animateEls.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});
</script>
@endpush
