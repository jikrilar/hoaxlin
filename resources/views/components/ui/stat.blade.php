{{--
    Metric block (T2.3): label, value, optional glossary term and description.
    The value stays a plain string; the caller formats numbers and never invents
    precision here.
--}}
@props(['label', 'value', 'term' => null, 'description' => null, 'tone' => 'neutral'])

@php
    if (! is_string($label) || trim($label) === '' || ! is_scalar($value) || is_bool($value)
        || ! in_array($tone, ['neutral', 'success', 'danger', 'warning', 'info'], true)
        || ($term !== null && (! is_string($term) || trim($term) === ''))) {
        throw new InvalidArgumentException('A stat requires a label, a scalar value and supported options.');
    }
@endphp

<div data-tone="{{ $tone }}" {{ $attributes->except('data-tone')->class(['hl-stat']) }}>
    <p class="hl-stat__label">
        @if($term !== null)
            <x-ui.term :key="$term">{{ $label }}</x-ui.term>
        @else
            {{ $label }}
        @endif
    </p>
    <p class="hl-stat__value">{{ $value }}</p>
    @if(filled($description))
        <p class="hl-stat__description">{{ $description }}</p>
    @endif
</div>
