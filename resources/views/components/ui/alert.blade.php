@props(['tone' => 'info', 'title' => null])

@php
    if (! in_array($tone, ['info', 'success', 'warning', 'danger'], true)) {
        throw new InvalidArgumentException('Unsupported alert tone.');
    }
@endphp

<div data-tone="{{ $tone }}" {{ $attributes->except('data-tone')->class(['hl-alert']) }}>
    @if(filled($title))
        <p class="hl-alert__title">{{ $title }}</p>
    @endif
    {{ $slot }}
</div>
