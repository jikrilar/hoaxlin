@extends('layouts.app')

@section('title', 'Lupa Kata Sandi — hoaxlin.id')
@section('description', 'Reset kata sandi akun hoaxlin.id kamu melalui email.')

@section('content')
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:6rem 1rem 3rem;">

    <!-- Background decoration -->
    <div class="glow-orb glow-orb-1" style="opacity:0.5;" aria-hidden="true"></div>
    <div class="glow-orb glow-orb-2" style="opacity:0.4;" aria-hidden="true"></div>

    <div style="width:100%; max-width:440px; position:relative;" class="animate-scale-in">

        <!-- Card -->
        <div class="glass-card-solid" style="padding:2.75rem;" role="main">

            <!-- Back to Login -->
            <a href="{{ route('login') }}" class="btn-ghost" style="display:inline-flex; margin-bottom:1.75rem; padding:0; color:var(--color-text-muted); font-size:0.875rem;" aria-label="Kembali ke halaman masuk">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Kembali ke halaman masuk
            </a>

            <!-- Icon & Title -->
            <div style="text-align:center; margin-bottom:2rem;">
                <div style="width:4rem; height:4rem; background:linear-gradient(135deg, rgba(99,102,241,0.15), rgba(6,182,212,0.1)); border:1px solid rgba(99,102,241,0.25); border-radius:1.25rem; display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem; font-size:1.75rem;" aria-hidden="true">🔑</div>
                <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:0.375rem;">Lupa Kata Sandi?</h1>
                <p style="color:var(--color-text-muted); font-size:0.9375rem; line-height:1.6;">
                    Masukkan email yang terdaftar. Kami akan mengirimkan tautan untuk mereset kata sandimu.
                </p>
            </div>

            <!-- Status Message -->
            @if (session('status'))
            <div style="padding:1rem 1.25rem; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); border-radius:0.875rem; margin-bottom:1.5rem; display:flex; gap:0.625rem; align-items:center;" role="status">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <p style="color:#34d399; font-size:0.875rem;">{{ session('status') }}</p>
            </div>
            @endif

            <!-- Error -->
            @if($errors->any())
            <div style="padding:0.875rem 1rem; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); border-radius:0.75rem; margin-bottom:1.5rem;" role="alert">
                @foreach($errors->all() as $error)
                <p style="color:#f87171; font-size:0.875rem;">{{ $error }}</p>
                @endforeach
            </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('password.email') }}" id="forgot-form" novalidate>
                @csrf

                <!-- Email -->
                <div style="margin-bottom:1.5rem;">
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
                        aria-describedby="{{ $errors->has('email') ? 'email-error' : 'email-hint' }}"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    >
                    @error('email')
                        <p id="email-error" class="auth-error" role="alert">{{ $message }}</p>
                    @else
                        <p id="email-hint" style="color:var(--color-text-muted); font-size:0.8125rem; margin-top:0.375rem;">
                            Masukkan email yang kamu gunakan saat mendaftar.
                        </p>
                    @enderror
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-primary" id="reset-btn" style="width:100%; justify-content:center; font-size:1rem; padding:0.875rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <span>Kirim Tautan Reset</span>
                </button>
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
                    <a href="{{ route('login') }}" style="color:var(--color-primary-light); font-weight:600; text-decoration:none; margin-left:0.25rem;">
                        Masuk Sekarang
                    </a>
                </p>
                <p style="color:var(--color-text-muted); font-size:0.875rem;">
                    Belum punya akun?
                    <a href="{{ route('register') }}" style="color:var(--color-primary-light); font-weight:600; text-decoration:none; margin-left:0.25rem;">
                        Daftar Gratis
                    </a>
                </p>
            </div>
        </div>

        <!-- Security note -->
        <p style="text-align:center; color:var(--color-text-muted); font-size:0.8125rem; margin-top:1.25rem; display:flex; align-items:center; justify-content:center; gap:0.375rem;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Tautan reset bersifat sementara & aman
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('forgot-form').addEventListener('submit', (e) => {
    const btn = document.getElementById('reset-btn');
    btn.disabled = true;
    btn.innerHTML = '<span>Mengirim...</span><div class="spinner" style="width:1rem;height:1rem;border-width:2px;" aria-hidden="true"></div>';
});
</script>
@endpush
