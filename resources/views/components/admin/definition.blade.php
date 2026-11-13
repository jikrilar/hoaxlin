@props(['tooltip'])
{{-- A small, keyboard-reachable "?" that explains a computed number (T3.2).
     Filament's own tooltip JS needs a button; the title attribute keeps the
     definition readable without JavaScript and for screen readers. --}}
<x-filament::icon-button
    icon="heroicon-m-question-mark-circle"
    color="gray"
    size="xs"
    :tooltip="$tooltip"
    :label="'Penjelasan: '.$tooltip"
    class="hl-admin-definition"
/>
