@extends('layouts.auth')

@section('title', 'Daftar Akun — hoaxlin.id')
@section('description', 'Buat akun gratis di hoaxlin.id untuk menyimpan riwayat pengecekan berita.')

@section('content')
<!-- Title -->
<div class="hl-auth-title">
    <h1>Buat Akun Gratis</h1>
    <p>Bergabung dan mulai cek berita lebih cerdas.</p>
</div>

<!-- Register Form -->
<form method="POST" action="{{ route('register.store') }}" id="register-form" data-password-hints="register" data-submit-label="Membuat akun..." novalidate>
    @csrf

    <!-- Name -->
    <x-ui.field id="name" label="Nama Lengkap" :error="$errors->first('name')">
        <x-forms.input type="text" name="name" :value="old('name')" required
            autocomplete="name" autofocus placeholder="Nama Kamu" />
    </x-ui.field>

    <!-- Email -->
    <x-ui.field id="email" label="Alamat Email" :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="old('email')" required
            autocomplete="email" placeholder="kamu@email.com" />
    </x-ui.field>

    <!-- Password -->
    <div style="margin-bottom:1.125rem;">
        <x-ui.field id="password" label="Kata Sandi" :error="$errors->first('password')">
            <x-forms.password name="password" required autocomplete="new-password" placeholder="Minimal 8 karakter" aria-describedby="password-strength" />
        </x-ui.field>

        <!-- Password Strength Indicator -->
        <div id="password-strength" style="margin-top:0.5rem; display:none;" aria-live="polite">
            <div style="display:flex; gap:0.25rem; margin-bottom:0.375rem;">
                <div id="ps-1" style="height:3px; flex:1; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
                <div id="ps-2" style="height:3px; flex:1; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
                <div id="ps-3" style="height:3px; flex:1; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
                <div id="ps-4" style="height:3px; flex:1; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
            </div>
            <span id="ps-label" style="font-size:0.75rem; color:var(--color-text-muted);"></span>
        </div>
    </div>

    <!-- Confirm Password -->
    <div style="margin-bottom:1.5rem;">
        <x-ui.field id="password_confirmation" label="Konfirmasi Kata Sandi" :error="$errors->first('password_confirmation')">
            <x-forms.password name="password_confirmation" required autocomplete="new-password" placeholder="Ulangi kata sandi" aria-describedby="confirm-match" />
        </x-ui.field>
        <p id="confirm-match" style="font-size:0.75rem; margin-top:0.375rem; display:none;" aria-live="polite"></p>
    </div>

    <!-- Terms -->
    <div class="hl-auth-choice">
        <input type="checkbox" id="terms" name="terms" required aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}" @if($errors->has('terms')) aria-describedby="terms-error" @endif
              style="width:1rem; height:1rem; accent-color:var(--color-primary); cursor:pointer; margin-top:2px; flex-shrink:0;">
        <label for="terms" style="color:var(--color-text-secondary); font-size:0.875rem; cursor:pointer; line-height:1.5;">
            Saya menyetujui
            <a href="{{ url('/kebijakan-privasi') }}" style="color:var(--color-primary-light); text-decoration:none; font-weight:500;">Kebijakan Privasi</a>
            dan memahami bahwa hasil deteksi bersifat indikatif.
        </label>
    </div>
    @error('terms')
        <p id="terms-error" class="auth-error" role="alert" style="margin-top:-1rem; margin-bottom:1rem;">{{ $message }}</p>
    @enderror

    <!-- Submit -->
    <button type="submit" class="btn-primary" id="register-btn" style="width:100%; justify-content:center; font-size:1rem; padding:0.875rem;">
        <span>Buat Akun Sekarang</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
</form>

<div style="display:flex; align-items:center; gap:1rem; margin:1.75rem 0;" aria-hidden="true">
    <div class="divider" style="flex:1;"></div>
    <span style="color:var(--color-text-muted); font-size:0.8125rem;">sudah punya akun?</span>
    <div class="divider" style="flex:1;"></div>
</div>

<div style="text-align:center;">
    <a href="{{ route('login') }}" class="btn-secondary" style="display:inline-flex; justify-content:center; width:100%;">
        Masuk ke Akun
    </a>
</div>
@endsection
