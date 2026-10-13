@props(['id', 'label', 'hint' => null, 'error' => null])

@php
    if (! is_string($id) || $id === '' || preg_match('/\s/', $id)
        || ! is_string($label) || $label === '') {
        throw new InvalidArgumentException('A field requires a unique control id and a visible label.');
    }
@endphp

<div {{ $attributes->class(['hl-field']) }}>
    <label class="hl-field__label" for="{{ $id }}">{{ $label }}</label>
    {{ $slot }}
    @if(filled($hint))
        <p class="hl-field__hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @if(filled($error))
        <p class="hl-field__error" id="{{ $id }}-error">{{ $error }}</p>
    @endif
</div>
