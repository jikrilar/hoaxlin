@props(['type' => 'text'])
@aware(['id' => null, 'label' => null, 'hint' => null, 'error' => null])

@php
    if (! is_string($id) || $id === ''
        || ! is_string($label) || $label === ''
        || $attributes->hasAny(['id', 'hint', 'error', 'label'])
        || ! in_array($type, ['text', 'email', 'password', 'search', 'url', 'number'], true)) {
        throw new InvalidArgumentException('Input must belong to a field with a matching id and supported type.');
    }

    $describedBy = implode(' ', array_unique(array_filter([
        ...preg_split('/\s+/', trim((string) $attributes->get('aria-describedby', ''))),
        filled($hint) ? $id.'-hint' : null,
        filled($error) ? $id.'-error' : null,
    ])));
    $invalid = filled($error) || $attributes->get('aria-invalid') === 'true';
@endphp

<input id="{{ $id }}" type="{{ $type }}" aria-invalid="{{ $invalid ? 'true' : 'false' }}"
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->except(['id', 'aria-describedby', 'aria-invalid'])->class(['hl-input']) }}>
