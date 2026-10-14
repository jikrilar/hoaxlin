<form id="form-{{ $suffix }}" action="{{ route('deteksi') }}" method="POST" data-submit-label="Mengirim..." data-url-submission
    @if($type === 'video_url') data-direct-url-options="{{ json_encode($submissionOptions['videoUrl']) }}" @endif>
    @csrf
    <fieldset data-submission-fields>
        <input type="hidden" name="input_type" value="{{ $type }}">
        <x-ui.field :id="$inputId" :label="$label" :hint="$hint"
            :error="$previousInputType === $type ? $errors->first('source_url') : null">
            <x-forms.input type="url" name="source_url" :value="$previousInputType === $type ? $previousSourceUrl : ''"
                required pattern="[Hh][Tt][Tt][Pp][Ss]?://.+" :maxlength="$submissionOptions[$type === 'video_url' ? 'videoUrl' : 'url']['maxLength']" placeholder="https://contoh.id/informasi"
                :aria-describedby="$inputId.'-client-error'" data-source-url />
            <p id="{{ $inputId }}-client-error" class="hl-field__error" data-url-error role="alert" hidden></p>
            <p class="hl-field__hint" data-url-preview></p>
        </x-ui.field>
        @include('partials.submission.security', ['suffix' => $suffix, 'type' => $type])
        <x-ui.button type="submit" class="w-full">Periksa informasi</x-ui.button>
        <p class="hl-field__hint" role="status" aria-live="polite" data-submission-status></p>
    </fieldset>
</form>
