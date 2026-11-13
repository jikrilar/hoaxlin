{{--
    Status badge (T2.3). Tone never carries meaning alone: every badge renders a
    decorative icon plus visible text, so colour-blind readers still get the label.
--}}
@props(['tone' => 'neutral'])

@php
    if (! in_array($tone, ['neutral', 'success', 'danger', 'warning', 'info'], true)) {
        throw new InvalidArgumentException('Unsupported badge tone.');
    }

    $text = trim((string) $slot);
    if ($text === '') {
        throw new InvalidArgumentException('A badge requires visible text.');
    }

    // Paths only; the wrapper supplies width/height and colour.
    $icon = match ($tone) {
        'success' => 'M20 6 9 17l-5-5',
        'danger' => 'M18 6 6 18M6 6l12 12',
        'warning' => 'M12 8v5M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        'info' => 'M12 16v-4M12 8h.01M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
        default => 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
    };
@endphp

<span data-tone="{{ $tone }}" {{ $attributes->except('data-tone')->class(['hl-badge']) }}>
    <svg class="hl-badge__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="{{ $icon }}"/>
    </svg>
    <span class="hl-badge__text">{{ $text }}</span>
</span>
