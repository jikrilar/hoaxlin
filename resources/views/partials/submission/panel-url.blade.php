<div id="input-panel-url" role="tabpanel" aria-labelledby="input-tab-url" tabindex="0"
    class="hl-tabs__panel hl-input-panel" @unless($active) hidden @endunless>
    <h3>Tautan artikel</h3>
    <fieldset data-input-fields @unless($active) disabled @endunless>
        <input type="hidden" name="input_type" value="url">
        @include('partials.submission.url', ['inputId' => 'url-input', 'label' => 'URL artikel', 'hint' => 'Gunakan HTTP atau HTTPS; maksimal '.$submissionOptions['url']['maxLength'].' karakter. Tautan diproses dan diperiksa kembali oleh server.', 'previous' => $previousInputType === 'url' ? $previousSourceUrl : ''])
    </fieldset>
</div>
