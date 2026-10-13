<form id="form-teks" action="{{ route('deteksi') }}" method="POST" data-text-submission data-submit-label="Mengirim...">
    @csrf
    <input type="hidden" name="input_type" value="text">

    <x-ui.field id="teks-input" label="Teks informasi"
        hint="Masukkan 50–50.000 karakter. Teks yang cukup membantu model membaca konteks."
        :error="$errors->first('raw_input')">
        <textarea id="teks-input" name="raw_input" class="hl-input hl-submission-textarea"
            placeholder="Tempel atau ketik informasi yang ingin diperiksa."
            required minlength="50" maxlength="50000"
            aria-invalid="{{ $errors->has('raw_input') ? 'true' : 'false' }}"
            aria-describedby="teks-input-hint teks-client-error{{ $errors->has('raw_input') ? ' teks-input-error' : '' }}">{{ $previousRawInput }}</textarea>
        <p id="teks-client-error" class="hl-field__error" data-text-error role="alert" hidden></p>
        <output id="teks-count" for="teks-input" class="hl-field__hint" data-text-count>{{ mb_strlen($previousRawInput) }} karakter</output>
    </x-ui.field>

    <input type="text" name="website" class="hl-submission-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="hl-submission-security">
        <x-ui.field id="captcha-teks" :label="'Keamanan: Berapa '.$captchaQuestion.'?'"
            hint="Jawab pertanyaan ini untuk mengirim pemeriksaan."
            :error="$errors->first('captcha_answer')">
            <x-forms.input type="number" name="captcha_answer" required inputmode="numeric" autocomplete="off" />
        </x-ui.field>
    </div>

    <x-ui.button type="submit" id="submit-teks" class="w-full">Periksa informasi</x-ui.button>
    <p class="hl-field__hint" role="status" aria-live="polite" data-submission-status></p>
</form>
