<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-legacy-tokens>
<head>
    @include('partials.head')
</head>
<body>
    <a class="hl-skip-link" href="#main-content">Lewati ke konten utama</a>

    <header>
        <!-- Navbar -->
        <nav class="navbar" id="main-navbar" aria-label="Main navigation">
            <div class="container-app">
                <div style="display:flex; align-items:center; justify-content:space-between;">

                    <!-- Logo -->
                    <a href="{{ url('/') }}" class="nav-logo" aria-label="hoaxlin.id beranda">
                        <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                            <svg width="28" height="28" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <circle cx="16" cy="16" r="14" fill="var(--ds-bg-selected)" opacity="0.15"/>
                                <path d="M8 12l8-5 8 5v8l-8 5-8-5V12z" stroke="var(--ds-action-primary)" stroke-width="2" fill="none"/>
                                <path d="M13 15l2 2 4-4" stroke="var(--ds-action-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            hoaxlin<span style="color:var(--ds-link); background:none;">.id</span>
                        </span>
                    </a>

                    <!-- Desktop Nav -->
                    <div id="nav-desktop" style=" align-items:center; gap:2rem;" aria-label="Desktop navigation">
                        <a href="{{ url('/') }}" class="nav-link {{ request()->is('/') ? 'active' : '' }}">Beranda</a>
                        <a href="{{ url('/cara-kerja') }}" class="nav-link {{ request()->is('cara-kerja') ? 'active' : '' }}">Cara Kerja</a>
                        <a href="{{ url('/tentang') }}" class="nav-link {{ request()->is('tentang') ? 'active' : '' }}">Tentang</a>
                    </div>

                    <!-- Auth Buttons -->
                    <div id="nav-auth" style="display:flex; align-items:center; gap:0.75rem;">
                        @auth
                            <!-- User dropdown -->
                            <div style="position:relative;" id="user-menu-wrapper">
                                <button id="user-menu-btn"
                                    type="button"
                                   style="display:flex; align-items:center; gap:0.625rem; padding:0.375rem 0.75rem 0.375rem 0.5rem; background:var(--ds-bg-selected); border:1px solid var(--ds-border-control); border-radius:2rem; cursor:pointer; transition:all 0.2s ease;"
                                    onmouseover="this.style.background='var(--ds-bg-selected)'" onmouseout="this.style.background='var(--ds-bg-selected)'"
                                    aria-expanded="false" aria-controls="user-dropdown">
                                    <div style="width:1.75rem; height:1.75rem; border-radius:50%; background:var(--ds-action-primary); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:0.75rem; font-weight:var(--ds-font-weight-semibold); color:var(--ds-text-inverse); flex-shrink:0;" aria-hidden="true">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                    </div>
                                    <span style="color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        {{ auth()->user()->name }}
                                    </span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--color-text-muted); transition:transform 0.2s;" id="user-menu-chevron" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                </button>

                                <!-- Dropdown -->
                                <div id="user-dropdown" style="display:none; position:absolute; top:calc(100% + 0.5rem); right:0; min-width:180px; background:var(--ds-bg-surface); border:1px solid var(--color-border); border-radius:0.875rem; padding:0.5rem; box-shadow:0 8px 32px rgb(var(--p-ink-rgb) / 10%); z-index:101; ">
                                    <a href="{{ route('profile') }}" class="dropdown-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                        Profil Saya
                                    </a>
                                    <a href="{{ route('riwayat') }}" class="dropdown-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Riwayat Pengecekan
                                    </a>
                                    <div style="height:1px; background:var(--color-border-light); margin:0.375rem 0;"></div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item" style="width:100%; text-align:left; background:none; border:none; cursor:pointer; color:var(--ds-danger-fg);">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                                            Keluar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="btn-ghost">Masuk</a>
                            <a href="{{ route('register') }}" class="btn-primary">
                                <span>Daftar Gratis</span>
                            </a>
                        @endauth

                        <!-- Mobile Menu Toggle -->
                        <button id="menu-toggle" class="btn-ghost" style="display:none; padding:0.5rem;" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobile-menu">
                            <svg id="menu-icon-open" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                            <svg id="menu-icon-close" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;" aria-hidden="true"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="mobile-menu" role="dialog" aria-modal="true" aria-label="Mobile navigation">
            <a href="{{ url('/') }}" class="nav-logo" style="font-size:1.75rem;">hoaxlin.id</a>
            <div style="display:flex; flex-direction:column; align-items:center; gap:1.5rem;">
                <a href="{{ url('/') }}" class="nav-link" style="font-size:1.25rem;">Beranda</a>
                <a href="{{ url('/cara-kerja') }}" class="nav-link" style="font-size:1.25rem;">Cara Kerja</a>
                <a href="{{ url('/tentang') }}" class="nav-link" style="font-size:1.25rem;">Tentang</a>
                @auth
                    <a href="{{ route('profile') }}" class="nav-link" style="font-size:1.25rem;">Profil Saya</a>
                    <a href="{{ route('riwayat') }}" class="nav-link" style="font-size:1.25rem;">Riwayat</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost" style="font-size:1.125rem; color:var(--ds-danger-fg);">Keluar</button></form>
                @else
                    <a href="{{ route('login') }}" class="btn-secondary">Masuk</a>
                    <a href="{{ route('register') }}" class="btn-primary"><span>Daftar Gratis</span></a>
                @endauth
            </div>
        </div>

    </header>

    <!-- Global Flash Messages -->
    @if(session('success') || session('error') || session('status'))
    <div id="flash-banner" data-flash style="position:fixed; top:5rem; left:50%; transform:translateX(-50%); z-index:150; min-width:320px; max-width:560px; width:calc(100% - 2rem); " role="status" aria-live="polite">
        @if(session('success'))
        <div style="padding:0.875rem 1.25rem; background:var(--ds-success-bg); border:1px solid var(--ds-success-fg); border-radius:0.875rem; display:flex; align-items:center; gap:0.75rem;  box-shadow:0 4px 24px rgb(var(--p-ink-rgb) / 10%);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ds-success-fg)" stroke-width="2.5" style="flex-shrink:0;" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span style="color:var(--ds-success-fg); font-size:0.875rem; font-weight:500; flex:1;">{{ session('success') }}</span>
            <button type="button" data-dismiss-flash style="background:none; border:none; color:var(--ds-success-fg); cursor:pointer; padding:0.125rem; opacity:0.7;" aria-label="Tutup">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        @elseif(session('error'))
        <div style="padding:0.875rem 1.25rem; background:var(--ds-danger-bg); border:1px solid var(--ds-danger-fg); border-radius:0.875rem; display:flex; align-items:center; gap:0.75rem;  box-shadow:0 4px 24px rgb(var(--p-ink-rgb) / 10%);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ds-danger-fg)" stroke-width="2.5" style="flex-shrink:0;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span style="color:var(--ds-danger-fg); font-size:0.875rem; font-weight:500; flex:1;">{{ session('error') }}</span>
            <button type="button" data-dismiss-flash style="background:none; border:none; color:var(--ds-danger-fg); cursor:pointer; padding:0.125rem; opacity:0.7;" aria-label="Tutup">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        @elseif(session('status'))
        <div style="padding:0.875rem 1.25rem; background:var(--ds-bg-selected); border:1px solid var(--ds-border-control); border-radius:0.875rem; display:flex; align-items:center; gap:0.75rem;  box-shadow:0 4px 24px rgb(var(--p-ink-rgb) / 10%);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ds-link)" stroke-width="2.5" style="flex-shrink:0;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span style="color:var(--color-primary-light); font-size:0.875rem; font-weight:500; flex:1;">{{ session('status') }}</span>
            <button type="button" data-dismiss-flash style="background:none; border:none; color:var(--color-primary-light); cursor:pointer; padding:0.125rem; opacity:0.7;" aria-label="Tutup">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        @endif
    </div>
    @endif

    <!-- Page Content -->
    <main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer" role="contentinfo">
        <div class="container-app" style="padding-top:3rem; padding-bottom:3rem;">
            <div class="footer-grid-4col" style="display:grid; gap:2rem; margin-bottom:2.5rem;">

                <!-- Brand -->
                <div style="grid-column: span 1;">
                    <div class="footer-logo" style="margin-bottom:1rem;">hoaxlin.id</div>
                    <p style="color:var(--color-text-muted); font-size:0.875rem; line-height:1.7; margin-bottom:1rem;">
                        Sistem deteksi berita hoax berbasis kecerdasan buatan dengan metode BERT untuk analisis teks mendalam bahasa Indonesia.
                    </p>
                    <div style="display:flex; gap:0.75rem;">
                        <div style="width:2rem; height:2rem; background:var(--ds-bg-selected); border:1px solid var(--color-border); border-radius:0.5rem; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s ease;" onmouseover="this.style.background='var(--ds-bg-selected)'" onmouseout="this.style.background='var(--ds-bg-selected)'" role="button" tabindex="0" aria-label="Twitter/X">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color:var(--color-text-secondary);" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.76l7.73-8.835L1.254 2.25H8.08l4.258 5.632zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </div>
                        <div style="width:2rem; height:2rem; background:var(--ds-bg-selected); border:1px solid var(--color-border); border-radius:0.5rem; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s ease;" onmouseover="this.style.background='var(--ds-bg-selected)'" onmouseout="this.style.background='var(--ds-bg-selected)'" role="button" tabindex="0" aria-label="GitHub">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color:var(--color-text-secondary);" aria-hidden="true"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        </div>
                    </div>
                </div>

                <!-- Links -->
                <div>
                    <h3 style="color:var(--color-text-primary); font-size:0.9375rem; font-weight:600; margin-bottom:1rem;">Fitur</h3>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:0.625rem;">
                        <li><a href="{{ url('/') }}#cek-berita" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Cek Teks</a></li>
                        <li><a href="{{ url('/') }}#cek-berita" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Cek Gambar</a></li>
                        <li><a href="{{ url('/') }}#cek-berita" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Cek Video</a></li>
                        <li><a href="{{ url('/') }}#cek-berita" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Cek Tautan</a></li>
                    </ul>
                </div>

                <div>
                    <h3 style="color:var(--color-text-primary); font-size:0.9375rem; font-weight:600; margin-bottom:1rem;">Informasi</h3>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:0.625rem;">
                        <li><a href="{{ url('/cara-kerja') }}" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Cara Kerja</a></li>
                        <li><a href="{{ url('/tentang') }}" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Tentang Kami</a></li>
                        <li><a href="{{ url('/kebijakan-privasi') }}" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Kebijakan Privasi</a></li>
                    </ul>
                </div>

                <div>
                    <h3 style="color:var(--color-text-primary); font-size:0.9375rem; font-weight:600; margin-bottom:1rem;">Akun</h3>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:0.625rem;">
                        @auth
                            <li><a href="{{ url('/riwayat') }}" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Riwayat Saya</a></li>
                        @else
                            <li><a href="{{ route('login') }}" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Masuk</a></li>
                            <li><a href="{{ route('register') }}" style="color:var(--color-text-muted); font-size:0.875rem; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='var(--color-text-secondary)'" onmouseout="this.style.color='var(--color-text-muted)'">Daftar</a></li>
                        @endauth
                    </ul>
                </div>
            </div>

            <div class="divider" style="margin-bottom:1.5rem;"></div>

            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
                <p style="color:var(--color-text-muted); font-size:0.8125rem;">
                    © {{ date('Y') }} hoaxlin.id — Tugas Akhir D3 Teknik Komputer. Dikembangkan oleh Muhamad Jikril Aryanda.
                </p>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <div style="width:8px; height:8px; background:var(--ds-success-fg); border-radius:50%; " aria-hidden="true"></div>
                    <span style="color:var(--color-text-muted); font-size:0.8125rem;">Sistem aktif</span>
                </div>
            </div>
        </div>
    </footer>

    @include('partials.runtime')
</body>
</html>
