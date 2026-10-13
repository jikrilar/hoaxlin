<form id="form-teks" action="{{ route('deteksi') }}" method="POST" data-text-submission data-submit-label="Mengirim...">
    @csrf
    <fieldset data-submission-fields>
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

    @include('partials.submission.security', ['suffix' => 'teks', 'type' => 'text'])

    <x-ui.button type="submit" id="submit-teks" class="w-full">Periksa informasi</x-ui.button>
    <p class="hl-field__hint" role="status" aria-live="polite" data-submission-status></p>
    </fieldset>
</form>
