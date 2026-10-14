@php
    // Error rendering must not need auth, session, Livewire, or a working asset manifest.
    try {
        $errorStyles = app(\Illuminate\Foundation\Vite::class)->asset('resources/css/errors.css');
    } catch (\Throwable) {
        $errorStyles = null;
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — hoaxlin.id</title>
    @if($errorStyles)
        <link rel="stylesheet" href="{{ $errorStyles }}">
    @endif
</head>
<body class="hl-error-shell">
    <a class="hl-skip-link" href="#main-content">Lewati ke konten utama</a>
    <header class="hl-error-header">
        <x-ui.link :href="route('home', [], false)" variant="tertiary" class="hl-wordmark" aria-label="hoaxlin.id — Beranda">hoaxlin.id</x-ui.link>
    </header>
    <main id="main-content" class="hl-error-main" tabindex="-1">
        <p class="hl-error-code">Kode HTTP @yield('code')</p>
        <h1 class="hl-heading-editorial">@yield('title')</h1>
        <p class="hl-error-message">@yield('message')</p>
        @hasSection('recovery')
            <p class="hl-error-hint">@yield('recovery')</p>
        @endif
        @yield('details')
        <div class="hl-error-actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <x-ui.link :href="route('home', [], false)" variant="primary">Kembali ke beranda</x-ui.link>
            @endif
        </div>
    </main>
</body>
</html>
