@props(['options', 'kind'])
@aware(['id' => null, 'label' => null, 'hint' => null, 'error' => null])

@php
    if (! is_string($id) || $id === '' || ! is_string($label) || $label === ''
        || ! in_array($kind, ['image', 'video'], true) || ! is_array($options)
        || ! is_array($options['accept'] ?? null) || ($options['maxBytes'] ?? 0) <= 0
        || $attributes->hasAny(['value', 'name', 'type', 'id', 'accept'])) {
        throw new InvalidArgumentException('Upload fields require a field context and supported policy options; file values cannot be restored.');
    }
    $describedBy = implode(' ', array_filter([
        filled($hint) ? $id.'-hint' : null,
        filled($error) ? $id.'-error' : null,
        $id.'-client-error',
    ]));
@endphp

<div class="hl-upload" data-upload-field data-kind="{{ $kind }}" data-file-options="{{ json_encode($options) }}">
    <input type="file" id="{{ $id }}" name="media_file" class="hl-input hl-upload__input"
        accept="{{ implode(',', $options['accept']) }}" required aria-invalid="{{ filled($error) ? 'true' : 'false' }}"
        aria-describedby="{{ $describedBy }}" data-file-input>
    <p class="hl-field__error" id="{{ $id }}-client-error" data-file-error role="alert" hidden></p>
    <p class="hl-field__hint" data-file-status role="status" aria-live="polite"></p>
    @if($kind === 'image')
        <img class="hl-upload__preview" data-file-preview alt="Pratinjau gambar yang dipilih" hidden>
    @else
        <video class="hl-upload__preview" data-file-preview controls preload="metadata" hidden></video>
    @endif
    <x-ui.button variant="tertiary" data-file-clear hidden>Hapus file terpilih</x-ui.button>
</div>
