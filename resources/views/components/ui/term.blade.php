{{--
    Inline glossary term (T1.1). Rules enforced by the plan and this component:
    1. Explain a term only on its FIRST occurrence per page; later mentions stay plain text.
    2. At most two explained terms per paragraph.
    3. Never place two explained terms back-to-back without a connector word.
    4. Never wrap a word the same sentence already explains (including h3/dt that is
       immediately followed by the explanation itself).

    Rendering stays inline: no box model that changes line height, no padding, and no
    padding/margin contribution to the sentence. The only visual marker is a dotted
    underline plus a small "?" superscript. The popover is absolutely positioned, so
    opening it never shifts the surrounding text.
--}}
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

    // Trim so the component never contributes leading/trailing whitespace to the sentence.
    $slotContent = trim((string) $slot);
    $fallbackText = $slotContent !== '' ? trim($slotContent) : ($entry['label'] ?? $normalizedKey);
    $description = is_array($entry) && isset($entry['description']) && is_string($entry['description'])
        ? trim($entry['description'])
        : '';

    static $termSequence = 0;
    $popoverId = $description !== ''
        ? 'term-popover-'.preg_replace('/[^a-z0-9_-]/i', '-', $normalizedKey).'-'.(++$termSequence)
        : null;
@endphp
@if ($popoverId !== null)<span {{ $attributes->class(['hl-term']) }} data-term="{{ $normalizedKey }}"><button type="button" class="hl-term__trigger" data-term-trigger aria-expanded="false" aria-describedby="{{ $popoverId }}" title="{{ $description }}">{{ $fallbackText }}<span class="hl-term__mark" aria-hidden="true">?</span></button><span id="{{ $popoverId }}" role="tooltip" class="hl-term__popover" data-term-popover hidden>{{ $description }}</span></span>@else<span {{ $attributes->class(['hl-term', 'hl-term--plain']) }}>{{ $fallbackText }}</span>@endif
