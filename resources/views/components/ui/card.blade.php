{{--
    Surface container (T2.3): header, body, footer.

    `interactive` turns the whole card into a single link target and requires
    `href`; the URL is validated exactly like <x-ui.link>, so a card cannot
    smuggle a javascript:/data: URL or nest links.

    Usage:
      <x-ui.card header="Judul kartu">Isi</x-ui.card>
      <x-ui.card header="Judul" footer="10 hasil">Isi</x-ui.card>
      <x-ui.card :interactive="true" href="..." aria-label="Buka">Isi</x-ui.card>

    Header/footer are attributes, not slots: Blade here compiles this file after
    @props(), so `$slot` is shadowed and named slots cannot be read inside @if.
--}}
@props(['interactive' => false, 'href' => null, 'header' => null, 'footer' => null])

@php
    if (! is_bool($interactive)) {
        throw new InvalidArgumentException('Card interactive state must be a boolean.');
    }
    foreach (['header' => $header, 'footer' => $footer] as $name => $value) {
        if ($value !== null && (! is_string($value) || trim($value) === '')) {
            throw new InvalidArgumentException("Card {$name} must be a non-empty string when provided.");
        }
    }

    $url = null;
    if ($href !== null) {
        $parts = is_string($href) ? parse_url($href) : false;
        if (! is_string($href) || $href === '' || preg_match('/[\x00-\x20\x7f]/', $href)
            || str_starts_with($href, '//') || $parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['', 'http', 'https'], true)
            || (isset($parts['scheme']) && empty($parts['host']))) {
            throw new InvalidArgumentException('Card links require a safe navigation URL.');
        }
        $url = $href;
    }

    if ($interactive && $url === null) {
        throw new InvalidArgumentException('An interactive card requires an href.');
    }

    $element = $url !== null ? 'a' : 'section';
    if ($url !== null) {
        $attributes = $attributes->merge(['href' => $url]);
    }
@endphp

<{{ $element }} @if($interactive) data-interactive @endif
    {{ $attributes->class(['hl-card', 'hl-card--interactive' => $interactive]) }}>
    @if($header !== null)
        <p class="hl-card__header">{{ $header }}</p>
    @endif
    <div class="hl-card__body">{{ $slot }}</div>
    @if($footer !== null)
        <p class="hl-card__footer">{{ $footer }}</p>
    @endif
</{{ $element }}>
