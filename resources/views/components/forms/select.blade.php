@aware(['id' => null, 'label' => null, 'hint' => null, 'error' => null])

@php
    if (! is_string($id) || $id === '' || ! is_string($label) || $label === ''
        || $attributes->hasAny(['id', 'hint', 'error', 'label'])) {
        throw new InvalidArgumentException('Select must belong to a field with a matching id and visible label.');
    }
    $describedBy = implode(' ', array_unique(array_filter([
        ...preg_split('/\s+/', trim((string) $attributes->get('aria-describedby', ''))),
        filled($hint) ? $id.'-hint' : null,
        filled($error) ? $id.'-error' : null,
    ])));
@endphp

<select id="{{ $id }}" aria-invalid="{{ filled($error) || $attributes->get('aria-invalid') === 'true' ? 'true' : 'false' }}"
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->except(['id', 'aria-describedby', 'aria-invalid'])->class(['hl-input']) }}>
    {{ $slot }}
</select>
