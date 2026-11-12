<div id="input-panel-video-url" role="tabpanel" aria-labelledby="input-tab-video-url" tabindex="0"
    class="hl-tabs__panel hl-input-panel" @unless($active) hidden @endunless>
    <h3>URL langsung audio/video</h3>
    <fieldset data-input-fields @unless($active) disabled @endunless>
        <input type="hidden" name="input_type" value="video_url">
        @include('partials.submission.url', ['inputId' => 'video-url-input', 'label' => 'URL langsung file audio/video', 'hint' => 'HTTP/HTTPS, maksimal '.$submissionOptions['videoUrl']['maxLength'].' karakter. Format: '.strtoupper(implode(', ', $submissionOptions['videoUrl']['extensions'])).'. Gunakan URL file, bukan halaman YouTube, TikTok, atau platform lain.', 'previous' => $previousInputType === 'video_url' ? $previousSourceUrl : '', 'direct' => true])
    </fieldset>
</div>
