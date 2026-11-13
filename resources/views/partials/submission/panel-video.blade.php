@php($videoOptions = $submissionOptions['video'])
<div id="input-panel-video" role="tabpanel" aria-labelledby="input-tab-video" tabindex="0"
    class="hl-tabs__panel hl-input-panel" @unless($active) hidden @endunless>
    <h3>Video</h3>
    <fieldset data-input-fields @unless($active) disabled @endunless>
        <input type="hidden" name="input_type" value="video">
        <x-ui.field id="video-file-input" label="Pilih file video"
            hint="{{ strtoupper(implode(', ', $videoOptions['extensions'])) }}; maksimal {{ $videoOptions['maxSizeLabel'] }} dan {{ $videoOptions['maxDurationSeconds'] }} detik."
            :error="$errors->first('media_file')">
            <x-forms.upload-field :options="$videoOptions" kind="video" />
        </x-ui.field>
        <p class="hl-field__hint">File divalidasi kembali oleh server. Jika pengiriman gagal, pilih ulang file.</p>
    </fieldset>
</div>
