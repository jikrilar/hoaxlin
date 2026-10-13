@extends('layouts.auth')

@section('title', 'Masuk — hoaxlin.id')
@section('description', 'Masuk ke akun hoaxlin.id untuk mengakses riwayat pengecekan berita.')

@section('content')
<!-- Title -->
<div class="hl-auth-title">
    <h1>Selamat Datang Kembali!</h1>
    <p>Masuk untuk mengakses riwayat pengecekan kamu.</p>
</div>

<!-- Login Form -->
<form method="POST" action="{{ route('login.store') }}" id="login-form" data-submit-label="Memproses..." novalidate>
    @csrf

    <!-- Email -->
    <x-ui.field id="email" label="Alamat Email" :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="old('email')" required
            autocomplete="email" autofocus placeholder="kamu@email.com" />
    </x-ui.field>

    <!-- Password -->
    <x-ui.field id="password" label="Kata Sandi" :error="$errors->first('password')">
        <x-forms.password name="password" required autocomplete="current-password" placeholder="Minimal 8 karakter" />
    </x-ui.field>
    @if(\Illuminate\Support\Facades\Route::has('password.request'))
        <div class="hl-auth-recovery"><x-ui.link :href="route('password.request')">Lupa kata sandi?</x-ui.link></div>
    @endif

    <!-- Remember Me -->
    <div class="hl-auth-choice">
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
@endsection
