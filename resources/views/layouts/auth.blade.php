<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="hl-auth-shell">
    <a class="hl-skip-link" href="#main-content">Lewati ke konten utama</a>
    <header class="hl-auth-header">
        <a class="hl-wordmark" href="{{ route('home') }}" aria-label="hoaxlin.id — Beranda">hoaxlin.id</a>
    </header>
    <div class="hl-auth-content">
        @include('partials.flash')
        <main id="main-content" class="hl-auth-main" tabindex="-1">
            @if($errors->any())
                @include('partials.auth.errors', ['targets' => $authErrorTargets ?? []])
            @endif
            @yield('content')
        </main>
    </div>
    <footer class="hl-auth-footer">
        <x-ui.link :href="route('home')" variant="tertiary">Kembali ke beranda</x-ui.link>
        <x-ui.link :href="route('kebijakan-privasi')" variant="tertiary">Kebijakan Privasi</x-ui.link>
    </footer>
    @include('partials.runtime')
</body>
</html>
