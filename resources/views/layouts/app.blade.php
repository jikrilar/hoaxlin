<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
    <a class="hl-skip-link" href="#main-content">Lewati ke konten utama</a>

    @include('partials.navigation')
    @include('partials.flash')

    <!-- Page Content -->
    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.runtime')
</body>
</html>
