<!-- ── Panel: Gambar ── -->
@auth
<div id="panel-gambar" class="tab-content {{ $activeTab === 'gambar' ? 'active' : '' }}" role="tabpanel" aria-labelledby="tab-gambar">
    <form id="form-gambar" action="{{ url('/deteksi') }}" method="POST" enctype="multipart/form-data" data-submit-label="Mengirim...">
        @csrf
        <input type="hidden" name="input_type" value="image">

        <label for="gambar-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">
            Unggah Foto / Tangkapan Layar Berita
        </label>

        <label class="upload-zone" id="gambar-zone" for="gambar-input" aria-label="Klik atau seret gambar ke sini"
             data-file-drop="gambar">
            <input type="file" id="gambar-input" name="media_file" accept="image/*" data-file-preview="gambar" required>
            <div class="upload-zone-icon" aria-hidden="true">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <div id="gambar-preview-label">
                <div class="upload-zone-title">Klik atau Seret Gambar ke Sini</div>
                <div class="upload-zone-subtitle">PNG, JPG, WEBP, HEIC hingga 10MB</div>
            </div>
            <img id="gambar-preview-img" src="" alt="" style="display:none; max-height:200px; border-radius:0.5rem; object-fit:contain;">
        </label>

        <div class="info-box" style="margin-top:1rem;" role="note">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span>Teks akan diekstraksi otomatis dari gambar menggunakan teknologi OCR (Optical Character Recognition) berbasis AI, lalu dianalisis oleh model BERT.</span>
        </div>

        <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div style="display:grid; grid-template-columns:1fr auto; gap:0.75rem; align-items:end; margin-top:1rem; padding:0.875rem; background:var(--ds-bg-selected); border:1px solid var(--ds-border-control); border-radius:0.75rem;">
            <div>
                <label for="captcha-gambar" style="display:block; color:var(--color-text-secondary); font-size:0.8125rem; font-weight:500; margin-bottom:0.375rem;">Keamanan: Berapa {{ $captchaQuestion }} <span style="color:var(--ds-danger-fg);">*</span></label>
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
<div id="panel-video" class="tab-content {{ $activeTab === 'video' ? 'active' : '' }}" role="tabpanel" aria-labelledby="tab-video">
    <form id="form-video" action="{{ url('/deteksi') }}" method="POST" enctype="multipart/form-data" data-video-max-bytes="{{ $videoMaxBytes }}" data-submit-label="Mengirim..." data-blocked-video-hosts='@json($blockedVideoHosts)'>
        @csrf
        <input type="hidden" name="input_type" id="video-input-type" value="{{ $videoUrlActive ? 'video_url' : 'video' }}">

        <!-- Video sub-tabs -->
        <div style="display:flex; gap:0.5rem; margin-bottom:1.25rem;" role="group" aria-label="Jenis input video">
            <button type="button" id="video-tab-upload" data-video-mode="upload"
               style="padding:0.5rem 1rem; border-radius:0.5rem; border:1px solid {{ $videoUrlActive ? 'var(--color-border)' : 'var(--ds-border-control)' }}; background:{{ $videoUrlActive ? 'transparent' : 'var(--ds-bg-selected)' }}; color:{{ $videoUrlActive ? 'var(--color-text-muted)' : 'var(--color-primary-light)' }}; font-size:0.875rem; font-weight:500; cursor:pointer; transition:all 0.2s;"
                aria-pressed="{{ $videoUrlActive ? 'false' : 'true' }}">
                Unggah File Video
            </button>
            <button type="button" id="video-tab-url" data-video-mode="url"
               style="padding:0.5rem 1rem; border-radius:0.5rem; border:1px solid {{ $videoUrlActive ? 'var(--ds-bg-selected)' : 'var(--color-border)' }}; background:{{ $videoUrlActive ? 'var(--ds-bg-selected)' : 'transparent' }}; color:{{ $videoUrlActive ? 'var(--color-primary-light)' : 'var(--color-text-muted)' }}; font-size:0.875rem; font-weight:500; cursor:pointer; transition:all 0.2s;"
                aria-pressed="{{ $videoUrlActive ? 'true' : 'false' }}">
                Tautan Video
            </button>
        </div>

        <!-- Upload Video -->
        <div id="video-upload-panel" @if ($videoUrlActive) style="display:none;" @endif>
            <label for="video-file-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">File Video</label>
            <label class="upload-zone" for="video-file-input" aria-label="Klik atau seret file video ke sini"
                 data-file-drop="video">
                <input type="file" id="video-file-input" name="media_file" accept="video/mp4,video/mpeg,video/webm,.mp4,.mpeg,.webm" data-file-preview="video" aria-describedby="video-size-error" @if (! $videoUrlActive) required @endif>
                <div class="upload-zone-icon" aria-hidden="true">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                </div>
                <div id="video-preview-label">
                    <div class="upload-zone-title">Klik atau Seret Video ke Sini</div>
                    <div class="upload-zone-subtitle">MP4, MPEG, atau WEBM hingga {{ $videoMaxLabel }}</div>
                </div>
            </label>
            <p id="video-size-error" role="alert" hidden style="color:var(--ds-danger-fg); font-size:0.875rem; margin-top:0.625rem;">Ukuran video melebihi batas {{ $videoMaxLabel }}. Pilih file yang lebih kecil.</p>
        </div>

        <!-- URL Video -->
        <div id="video-url-panel" @if (! $videoUrlActive) style="display:none;" @endif>
            <label for="video-url-input" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; margin-bottom:0.625rem;">URL Langsung File Audio/Video</label>
            <input type="url" id="video-url-input" name="source_url" class="form-input"
                   placeholder="https://cdn.example.com/rekaman.mp4"
                   aria-describedby="video-url-hint video-url-platform-error" value="{{ $previousInputType === 'video_url' ? $previousSourceUrl : '' }}" @if ($videoUrlActive) required @endif>
            <p id="video-url-hint" style="color:var(--color-text-muted); font-size:0.75rem; margin-top:0.5rem;">Gunakan tautan langsung ke file, bukan halaman YouTube atau media sosial. Mendukung FLAC, MP3, MP4, MPEG, MPGA, M4A, OGG, WAV, dan WEBM hingga {{ $videoMaxLabel }}.</p>
            <p id="video-url-platform-error" role="alert" hidden style="color:var(--ds-danger-fg); font-size:0.875rem; margin-top:0.625rem;">Platform video tidak didukung. Gunakan URL langsung ke file audio/video.</p>
        </div>

        <div class="warning-box" style="margin-top:1rem;" role="note">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>Proses video memerlukan waktu lebih lama untuk transkripsi. Tetap buka halaman hasil untuk melihat progres; pengguna masuk juga dapat membukanya kembali dari riwayat.</span>
        </div>

        <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div style="display:grid; grid-template-columns:1fr auto; gap:0.75rem; align-items:end; margin-top:1rem; padding:0.875rem; background:var(--ds-bg-selected); border:1px solid var(--ds-border-control); border-radius:0.75rem;">
            <div>
                <label for="captcha-video" style="display:block; color:var(--color-text-secondary); font-size:0.8125rem; font-weight:500; margin-bottom:0.375rem;">Keamanan: Berapa {{ $captchaQuestion }} <span style="color:var(--ds-danger-fg);">*</span></label>
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
<div id="panel-url" class="tab-content {{ $activeTab === 'url' ? 'active' : '' }}" role="tabpanel" aria-labelledby="tab-url">
    <form id="form-url" action="{{ url('/deteksi') }}" method="POST" data-submit-label="Mengirim...">
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
            value="{{ $previousInputType === 'url' ? $previousSourceUrl : '' }}"
            required
            aria-describedby="url-hint"
        >
        <p id="url-hint" style="color:var(--color-text-muted); font-size:0.8125rem; margin-top:0.5rem;">
            Tempel tautan artikel berita penuh. Sistem akan mengekstrak konten artikelnya secara otomatis.
        </p>

        <!-- URL Preview -->
        <div id="url-preview" style="display:none; margin-top:1rem; padding:1rem; background:var(--ds-bg-surface); border:1px solid var(--color-border); border-radius:0.875rem;" aria-live="polite">
            <div style="display:flex; gap:0.75rem; align-items:flex-start;">
                <div style="width:2.5rem; height:2.5rem; background:var(--ds-bg-selected); border-radius:0.5rem; display:flex; align-items:center; justify-content:center; flex-shrink:0;" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                </div>
                <div>
                    <div id="url-domain" style="color:var(--color-primary-light); font-size:0.875rem; font-weight:600;"></div>
                    <div id="url-full" style="color:var(--color-text-muted); font-size:0.8125rem; word-break:break-all; margin-top:0.25rem;"></div>
                </div>
            </div>
        </div>

        <input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div style="display:grid; grid-template-columns:1fr auto; gap:0.75rem; align-items:end; margin-top:1rem; padding:0.875rem; background:var(--ds-bg-selected); border:1px solid var(--ds-border-control); border-radius:0.75rem;">
            <div>
                <label for="captcha-url" style="display:block; color:var(--color-text-secondary); font-size:0.8125rem; font-weight:500; margin-bottom:0.375rem;">Keamanan: Berapa {{ $captchaQuestion }} <span style="color:var(--ds-danger-fg);">*</span></label>
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

@endauth
