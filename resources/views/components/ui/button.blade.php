@props(['variant' => 'primary', 'type' => 'button', 'disabled' => false, 'loading' => false])

@php
    if (! in_array($variant, ['primary', 'secondary', 'tertiary', 'destructive'], true)
        || ! in_array($type, ['button', 'submit', 'reset'], true)
        || ! is_bool($disabled) || ! is_bool($loading)) {
        throw new InvalidArgumentException('Invalid button variant, type or state.');
    }
@endphp

<button type="{{ $type }}" data-variant="{{ $variant }}"
    @disabled($disabled || $loading) @if($loading) aria-busy="true" @endif
    {{ $attributes->except(['aria-busy', 'data-variant'])->class(['hl-button']) }}>
    @if($loading)
        <span class="hl-button__spinner" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</button>
