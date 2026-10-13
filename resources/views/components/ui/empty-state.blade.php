@props(['message', 'title' => null])

<div {{ $attributes->class(['hl-empty-state']) }}>
    @if(filled($title))
        <h2 class="hl-empty-state__title">{{ $title }}</h2>
    @endif
    <p class="hl-empty-state__message">{{ $message }}</p>
    @isset($actions)
        <div class="hl-empty-state__actions">{{ $actions }}</div>
    @endisset
</div>
