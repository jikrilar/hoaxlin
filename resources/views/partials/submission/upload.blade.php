@php
    $options = $submissionOptions[$type];
    $formats = strtoupper(implode(', ', $options['extensions']));
    $hint = $type === 'image'
        ? $formats.'; maksimal '.$options['maxSizeLabel'].', '.$options['maxWidth'].' × '.$options['maxHeight'].' piksel.'
        : $formats.'; maksimal '.$options['maxSizeLabel'].' dan '.$options['maxDurationSeconds'].' detik.';
@endphp
<form id="form-{{ $suffix }}" action="{{ route('deteksi') }}" method="POST" enctype="multipart/form-data" data-submit-label="Mengirim..." data-upload-submission>
    @csrf
    <fieldset data-submission-fields>
        <input type="hidden" name="input_type" value="{{ $type }}">
        <x-ui.field :id="$inputId" :label="$label" :hint="$hint"
            :error="$previousInputType === $type ? $errors->first('media_file') : null">
            <x-forms.upload-field :options="$options" :kind="$type" />
        </x-ui.field>
        <p class="hl-field__hint">File divalidasi kembali oleh server. Jika pengiriman gagal, pilih ulang file.</p>
        @include('partials.submission.security', ['suffix' => $suffix, 'type' => $type])
        <x-ui.button type="submit" class="w-full">Periksa informasi</x-ui.button>
        <p class="hl-field__hint" role="status" aria-live="polite" data-submission-status></p>
    </fieldset>
</form>
