@props(['autocomplete' => 'new-password'])
@aware(['id' => null])

@php
    if (! in_array($autocomplete, ['current-password', 'new-password'], true)
        || $attributes->hasAny(['value', 'type'])) {
        throw new InvalidArgumentException('Password fields require password autocomplete and cannot restore a value.');
    }
@endphp

<div class="hl-password">
    <x-forms.input type="password" :autocomplete="$autocomplete" {{ $attributes->class(['hl-password__input']) }} />
    <button type="button" class="hl-password__toggle" data-password-toggle="{{ $id }}"
        aria-label="Tampilkan kata sandi" aria-controls="{{ $id }}" aria-pressed="false" hidden>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
        </svg>
    </button>
</div>
