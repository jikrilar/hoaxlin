@auth
    <section id="panel-url" class="hl-input-panel" data-input-panel="url">
        <h3>Tautan artikel</h3>
        @include('partials.submission.url', ['suffix' => 'url', 'type' => 'url', 'inputId' => 'url-input', 'label' => 'URL artikel', 'hint' => 'Gunakan HTTP atau HTTPS; maksimal '.$submissionOptions['url']['maxLength'].' karakter. Tautan diproses dan diperiksa kembali oleh server.'])
    </section>
    <section id="panel-gambar" class="hl-input-panel" data-input-panel="gambar">
        <h3>Gambar</h3>
        @include('partials.submission.upload', ['suffix' => 'gambar', 'type' => 'image', 'inputId' => 'gambar-input', 'label' => 'Pilih gambar informasi'])
    </section>
    <section id="panel-video" class="hl-input-panel" data-input-panel="video">
        <h3>Video</h3>
        <nav class="hl-input-selector" aria-label="Sumber video" data-video-selector>
            <a id="video-tab-upload" class="hl-input-option" href="#video-upload-panel" data-video-mode="upload">Unggah video</a>
            <a id="video-tab-url" class="hl-input-option" href="#video-url-panel" data-video-mode="url">URL langsung audio/video</a>
        </nav>
        <div id="video-upload-panel" data-video-panel="upload">
            <h4>Unggah video</h4>
            @include('partials.submission.upload', ['suffix' => 'video', 'type' => 'video', 'inputId' => 'video-file-input', 'label' => 'Pilih file video'])
        </div>
        <div id="video-url-panel" data-video-panel="url">
            <h4>URL langsung audio/video</h4>
            @include('partials.submission.url', ['suffix' => 'video-url', 'type' => 'video_url', 'inputId' => 'video-url-input', 'label' => 'URL langsung file audio/video', 'hint' => 'HTTP/HTTPS, maksimal '.$submissionOptions['videoUrl']['maxLength'].' karakter. Format: '.strtoupper(implode(', ', $submissionOptions['videoUrl']['extensions'])).'. Gunakan URL file, bukan halaman YouTube, TikTok, atau platform lain.'])
        </div>
    </section>
@endauth
