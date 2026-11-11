@props(['key' => ''])

@php
    $normalizedKey = is_string($key) ? trim($key) : '';
    $glossary = config('glossary');
    if (! is_array($glossary)) {
        $glossaryPath = base_path('config/glossary.php');
        $glossary = is_file($glossaryPath) ? require $glossaryPath : [];
    }

    $entry = ($normalizedKey !== '' && isset($glossary[$normalizedKey]) && is_array($glossary[$normalizedKey]))
        ? $glossary[$normalizedKey]
        : null;

    $slotContent = trim((string) $slot);
    $fallbackText = $slotContent !== '' ? $slot : ($entry['label'] ?? $normalizedKey);
    $description = is_array($entry) && isset($entry['description']) && is_string($entry['description'])
        ? trim($entry['description'])
        : '';

    static $termSequence = 0;
    $popoverId = $description !== ''
        ? 'term-popover-'.preg_replace('/[^a-z0-9_-]/i', '-', $normalizedKey).'-'.(++$termSequence)
        : null;
@endphp

@if ($popoverId !== null)
    <span {{ $attributes->class(['hl-term']) }} data-term="{{ $normalizedKey }}">
        <button
            type="button"
            class="hl-term__trigger"
            data-term-trigger
            aria-expanded="false"
            aria-describedby="{{ $popoverId }}"
            title="{{ $description }}"
        >
            <span class="hl-term__label">{{ $fallbackText }}</span>
            <span class="hl-term__icon" aria-hidden="true">?</span>
        </button>
        <span
            id="{{ $popoverId }}"
            role="tooltip"
            class="hl-term__popover"
            data-term-popover
            hidden
        >{{ $description }}</span>
    </span>
@else
    <span {{ $attributes->class(['hl-term', 'hl-term--plain']) }}>{{ $fallbackText }}</span>
@endif
