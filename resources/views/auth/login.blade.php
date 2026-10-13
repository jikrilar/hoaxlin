@extends('layouts.app')

@section('title', 'Masuk — hoaxlin.id')
@section('description', 'Masuk ke akun hoaxlin.id untuk mengakses riwayat pengecekan berita.')

@section('content')
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:6rem 1rem 3rem; width:100%; box-sizing:border-box; position:relative; ">

    <!-- Background decoration -->


    <div style="width:100%; max-width:440px; margin:0 auto; position:relative; transform-origin:center;">

        <!-- Card -->
        <div class="glass-card-solid" style="padding:clamp(var(--ds-space-4), 4vw, 2.75rem);">

            <!-- Logo & Title -->
            <div style="text-align:center; margin-bottom:2.25rem;">
                <a href="{{ url('/') }}" class="nav-logo" style="display:inline-block; font-size:1.75rem; margin-bottom:1rem;" aria-label="hoaxlin.id beranda">hoaxlin.id</a>
                <h1 style="font-size:1.5rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.375rem;">Selamat Datang Kembali!</h1>
                <p style="color:var(--color-text-muted); font-size:0.9375rem;">Masuk untuk mengakses riwayat pengecekan kamu.</p>
            </div>

            <!-- Error Alert -->
            @if($errors->any())
            <div style="padding:0.875rem 1rem; background:var(--ds-danger-bg); border:1px solid var(--ds-danger-fg); border-radius:0.75rem; margin-bottom:1.5rem; display:flex; gap:0.625rem; align-items:flex-start;" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ds-danger-fg)" stroke-width="2" style="flex-shrink:0; margin-top:1px;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    @foreach($errors->all() as $error)
                    <p style="color:var(--ds-danger-fg); font-size:0.875rem; line-height:1.4;">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('login.store') }}" id="login-form" data-submit-label="Memproses..." novalidate>
                @csrf

                <!-- Email -->
                <div style="margin-bottom:1.25rem;">
                    <label for="email" class="auth-label">Alamat Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="auth-input"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        autofocus
                        placeholder="kamu@email.com"
                        aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    >
                    @error('email')
                        <p id="email-error" class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div style="margin-bottom:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                        <label for="password" class="auth-label" style="margin-bottom:0;">Kata Sandi</label>
                        @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" style="color:var(--color-primary-light); font-size:0.8125rem; text-decoration:none; font-weight:500;">Lupa kata sandi?</a>
                        @endif
                    </div>
                    <div style="position:relative;">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="auth-input"
                            required
                            autocomplete="current-password"
                            placeholder="Minimal 8 karakter"
                           style="padding-right:3rem;"
                            aria-describedby="{{ $errors->has('password') ? 'password-error' : '' }}"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        >
                        <button type="button" id="toggle-password" data-password-toggle="password"
                               style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer; padding:0.25rem;"
                                aria-label="Tampilkan/sembunyikan kata sandi">
                            <svg id="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p id="password-error" class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div style="display:flex; align-items:center; gap:0.625rem; margin-bottom:1.5rem;">
                    <input type="checkbox" id="remember_me" name="remember"
                          style="width:1rem; height:1rem; accent-color:var(--color-primary); cursor:pointer;">
                    <label for="remember_me" style="color:var(--color-text-secondary); font-size:0.875rem; cursor:pointer;">
                        Ingat saya selama 30 hari
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary" id="login-btn" style="width:100%; justify-content:center; font-size:1rem; padding:0.875rem;">
                    <span>Masuk ke Akun</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                </button>
            </form>

            <!-- Divider -->
            <div style="display:flex; align-items:center; gap:1rem; margin:1.75rem 0;" aria-hidden="true">
                <div class="divider" style="flex:1;"></div>
                <span style="color:var(--color-text-muted); font-size:0.8125rem;">atau</span>
                <div class="divider" style="flex:1;"></div>
            </div>

            <!-- Register Link -->
            <p style="text-align:center; color:var(--color-text-muted); font-size:0.9375rem;">
                Belum punya akun?
                <a href="{{ route('register') }}" style="color:var(--color-primary-light); font-weight:600; text-decoration:none; margin-left:0.25rem;">
                    Daftar Gratis
                </a>
            </p>

            <!-- Guest option -->
            <div style="margin-top:1rem; text-align:center;">
                <a href="{{ url('/') }}" style="color:var(--color-text-muted); font-size:0.8125rem; text-decoration:none;">
                    Lanjutkan sebagai tamu →
                </a>
            </div>
        </div>

        <!-- Security note -->
        <p style="text-align:center; color:var(--color-text-muted); font-size:0.8125rem; margin-top:1.25rem; display:flex; align-items:center; justify-content:center; gap:0.375rem;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Koneksi aman & terenkripsi
        </p>
    </div>
</div>
@endsection
