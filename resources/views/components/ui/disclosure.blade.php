@props(['title', 'open' => false])

@php
    if (! is_string($title) || $title === '' || ! is_bool($open)) {
        throw new InvalidArgumentException('Disclosure requires a title and a boolean open state.');
    }
@endphp

<details @if($open) open @endif {{ $attributes->class(['hl-disclosure']) }}>
    <summary class="hl-disclosure__summary">{{ $title }}</summary>
    <div class="hl-disclosure__content">{{ $slot }}</div>
</details>
