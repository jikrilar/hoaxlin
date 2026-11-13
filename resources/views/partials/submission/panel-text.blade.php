<div id="input-panel-text" role="tabpanel" aria-labelledby="input-tab-text" tabindex="0"
    class="hl-tabs__panel hl-input-panel" @unless($active) hidden @endunless>
    <h3>Teks</h3>
    <fieldset data-input-fields @unless($active) disabled @endunless>
        <input type="hidden" name="input_type" value="text">
        <x-ui.field id="teks-input" label="Teks informasi"
            hint="Masukkan 50–50.000 karakter. Teks yang lengkap membantu model membaca konteks informasi."
            :error="$errors->first('raw_input')">
            <textarea id="teks-input" name="raw_input" class="hl-input hl-submission-textarea"
                placeholder="Tempel atau ketik informasi yang ingin Anda periksa."
                required minlength="50" maxlength="50000"
                aria-invalid="{{ $errors->has('raw_input') ? 'true' : 'false' }}"
                aria-describedby="teks-input-hint teks-client-error{{ $errors->has('raw_input') ? ' teks-input-error' : '' }}"
                data-text-input>{{ $previousRawInput }}</textarea>
            <p id="teks-client-error" class="hl-field__error" data-text-error role="alert" hidden></p>
            <output id="teks-count" for="teks-input" class="hl-field__hint" data-text-count>{{ mb_strlen($previousRawInput) }} karakter</output>
        </x-ui.field>
    </fieldset>
</div>
