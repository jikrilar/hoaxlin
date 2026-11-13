@php($imageOptions = $submissionOptions['image'])
<div id="input-panel-image" role="tabpanel" aria-labelledby="input-tab-image" tabindex="0"
    class="hl-tabs__panel hl-input-panel" @unless($active) hidden @endunless>
    <h3>Gambar</h3>
    <fieldset data-input-fields @unless($active) disabled @endunless>
        <input type="hidden" name="input_type" value="image">
        <x-ui.field id="gambar-input" label="Pilih gambar informasi"
            hint="{{ strtoupper(implode(', ', $imageOptions['extensions'])) }}; maksimal {{ $imageOptions['maxSizeLabel'] }}, {{ $imageOptions['maxWidth'] }} × {{ $imageOptions['maxHeight'] }} piksel."
            :error="$errors->first('media_file')">
            <x-forms.upload-field :options="$imageOptions" kind="image" />
        </x-ui.field>
        <p class="hl-field__hint">File divalidasi kembali oleh server. Jika pengiriman gagal, pilih ulang file.</p>
    </fieldset>
</div>
