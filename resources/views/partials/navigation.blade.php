@php
    $navigationUser = auth()->user();
    $primaryLinks = [
        ['route' => 'home', 'label' => 'Beranda'],
        ['route' => 'cara-kerja', 'label' => 'Cara kerja'],
        ['route' => 'tentang', 'label' => 'Tentang'],
    ];
@endphp

<header class="hl-header" data-navigation>
    <div class="container-app hl-header__inner">
        <a href="{{ route('home') }}" class="hl-wordmark" data-brand aria-label="hoaxlin.id beranda">hoaxlin.id</a>
        <button type="button" class="hl-nav-toggle" data-mobile-toggle aria-controls="public-navigation" aria-expanded="false" hidden>
            Menu
            <svg class="hl-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <nav id="public-navigation" class="hl-nav-panel" data-navigation-panel aria-label="Navigasi utama">
            <ul class="hl-nav-links">
                @foreach($primaryLinks as $link)
                    <li><a class="hl-nav-link" href="{{ route($link['route']) }}" @if(request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
            @if($navigationUser)
                <details class="hl-account" data-account>
                    <summary class="hl-nav-link hl-account__toggle" aria-controls="account-links" aria-label="Akun {{ $navigationUser->name }}">
                        Akun <span class="hl-account__name" aria-hidden="true">{{ $navigationUser->name }}</span>
                        <svg class="hl-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div id="account-links" class="hl-account__panel">
                        <a class="hl-nav-link" href="{{ route('profile') }}" @if(request()->routeIs('profile', 'profile.*')) aria-current="page" @endif>Profil saya</a>
                        @if($navigationUser->hasVerifiedEmail())
                            <a class="hl-nav-link" href="{{ route('riwayat') }}" @if(request()->routeIs('riwayat', 'riwayat.*')) aria-current="page" @endif>Riwayat pemeriksaan</a>
                        @else
                            <p class="hl-account__hint">Verifikasi email untuk mengakses riwayat pemeriksaan.</p>
                            <a class="hl-nav-link" href="{{ route('verification.notice') }}" @if(request()->routeIs('verification.notice')) aria-current="page" @endif>Verifikasi email</a>
                        @endif
                        @if($navigationUser->is_admin)
                            <a class="hl-nav-link" href="{{ route('filament.admin.pages.dashboard') }}">Panel admin</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="hl-nav-link hl-nav-link--danger">Keluar</button>
                        </form>
                    </div>
                </details>
            @else
                <div class="hl-nav-auth">
                    <a class="hl-nav-link" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Masuk</a>
                    <a class="hl-nav-link hl-nav-link--primary" href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif>Daftar</a>
                </div>
            @endif
        </nav>
    </div>
</header>
