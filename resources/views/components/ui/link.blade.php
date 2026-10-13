@props(['href', 'variant' => 'inline'])

@php
    $url = is_string($href) ? parse_url($href) : false;
    if (! in_array($variant, ['inline', 'primary', 'secondary', 'tertiary'], true)
        || ! is_string($href) || $href === '' || preg_match('/[\x00-\x20\x7f]/', $href)
        || str_starts_with($href, '//')
        || $url === false
        || ! in_array(strtolower($url['scheme'] ?? ''), ['', 'http', 'https'], true)
        || (isset($url['scheme']) && empty($url['host']))
        || $attributes->has('disabled') || $attributes->has('aria-disabled')) {
        throw new InvalidArgumentException('Links require a safe navigation URL and a supported variant.');
    }

    if ($attributes->get('target') === '_blank') {
        $rel = implode(' ', array_unique(array_filter(preg_split('/\s+/', trim((string) $attributes->get('rel', '').' noopener noreferrer')))));
        $attributes = $attributes->except('rel')->merge(['rel' => $rel]);
    }
@endphp

<a href="{{ $href }}" data-variant="{{ $variant }}"
    {{ $attributes->except('data-variant')->class(['hl-link' => $variant === 'inline', 'hl-button' => $variant !== 'inline']) }}>
    {{ $slot }}
</a>
