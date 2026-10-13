@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi — hoaxlin.id')
@section('description', 'Reset kata sandi akun hoaxlin.id kamu melalui email.')

@section('content')
<!-- Back to Login -->
<a href="{{ route('login') }}" class="btn-ghost" style="display:inline-flex; margin-bottom:1.75rem; padding:0; color:var(--color-text-muted); font-size:0.875rem;" aria-label="Kembali ke halaman masuk">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    Kembali ke halaman masuk
</a>

<!-- Title -->
<div class="hl-auth-title">
    <h1>Lupa Kata Sandi?</h1>
    <p style="color:var(--color-text-muted); font-size:0.9375rem; line-height:1.6;">
        Masukkan email yang terdaftar. Kami akan mengirimkan tautan untuk mereset kata sandimu.
    </p>
</div>

<!-- Form -->
<form method="POST" action="{{ route('password.email') }}" id="forgot-form" data-submit-label="Mengirim..." novalidate>
    @csrf

    <x-ui.field id="email" label="Alamat Email"
        hint="Masukkan email yang kamu gunakan saat mendaftar."
        :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="old('email')"
            required autocomplete="email" autofocus placeholder="kamu@email.com" />
    </x-ui.field>

    <!-- Submit -->
    <x-ui.button type="submit" id="reset-btn" class="w-full">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <span>Kirim Tautan Reset</span>
    </x-ui.button>
</form>

<!-- Divider -->
<div style="display:flex; align-items:center; gap:1rem; margin:1.75rem 0;" aria-hidden="true">
    <div class="divider" style="flex:1;"></div>
    <span style="color:var(--color-text-muted); font-size:0.8125rem;">atau</span>
    <div class="divider" style="flex:1;"></div>
</div>

<!-- Links -->
<div style="text-align:center;">
    <p style="color:var(--color-text-muted); font-size:0.9375rem; margin-bottom:0.75rem;">
        Ingat kata sandimu?
        <x-ui.link :href="route('login')">
            Masuk Sekarang
        </x-ui.link>
    </p>
    <p style="color:var(--color-text-muted); font-size:0.875rem;">
        Belum punya akun?
        <x-ui.link :href="route('register')">
            Daftar Gratis
        </x-ui.link>
    </p>
</div>
@endsection
