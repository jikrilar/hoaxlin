{{--
    Single submission form (T2.5): one CSRF token, one honeypot, one CAPTCHA,
    one multipart enctype. Tabs only choose which panel is submitted; the backend
    contract (input_type + raw_input/source_url/media_file) is unchanged.

    Without JS every panel is rendered server-side; only the active one is
    enabled, so exactly one input_type can be submitted.
--}}
@php
    $suffixMap = [
        'text' => 'teks',
        'url' => 'url',
        'image' => 'gambar',
        'video' => 'video',
        'video-url' => 'video-url',
    ];
@endphp

<form id="form-submission" action="{{ route('deteksi') }}" method="POST" enctype="multipart/form-data"
    data-submit-label="Mengirim..." data-submission-form data-active-tab="{{ $activeTabKey }}">
    @csrf
    <input type="text" name="website" value="" class="hl-submission-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
    {{-- New form keep the flag; authenticated tabs expose the machine-readable state. --}}
    @auth
        <input type="hidden" name="input_type" value="text" data-default-input-type>
    @endauth

    @isset($prelude)
        {{ $prelude }}
    @endisset

    @auth
        @include('partials.submission.tabs', [
            'inputTabs' => $inputTabs,
            'activeTabKey' => $activeTabKey,
            'previousInputType' => $previousInputType,
            'previousSourceUrl' => $previousSourceUrl,
        ])
    @else
        @include('partials.submission.panel-text', ['active' => true, 'previousRawInput' => $previousRawInput])
    @endauth

    {{-- One CAPTCHA for the whole form, issued once per page load. --}}
    <div class="hl-submission-security">
        <x-ui.field id="captcha-answer" :label="'Keamanan: '.$captchaQuestion"
            hint="Jawab soal singkat ini agar kami tahu Anda bukan robot."
            :error="$errors->first('captcha_answer')">
            <x-forms.input type="number" name="captcha_answer" required inputmode="numeric" autocomplete="off" />
        </x-ui.field>
    </div>

    <x-ui.button type="submit" class="w-full">Periksa informasi</x-ui.button>
    <p class="hl-field__hint" role="status" aria-live="polite" data-submission-status></p>
</form>
