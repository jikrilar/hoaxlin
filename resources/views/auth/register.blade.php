@extends('layouts.app')

@section('title', 'Daftar Akun — hoaxlin.id')
@section('description', 'Buat akun gratis di hoaxlin.id untuk menyimpan riwayat pengecekan berita.')

@section('content')
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:6rem 1rem 3rem; width:100%; box-sizing:border-box;">

    <div class="glow-orb glow-orb-1" style="opacity:0.5;" aria-hidden="true"></div>
    <div class="glow-orb glow-orb-2" style="opacity:0.4;" aria-hidden="true"></div>

    <div style="width:100%; max-width:480px; margin:0 auto; position:relative; transform-origin:center;" class="animate-scale-in">

        <div class="glass-card-solid" style="padding:2.75rem;" role="main">

            <!-- Logo & Title -->
            <div style="text-align:center; margin-bottom:2.25rem;">
                <a href="{{ url('/') }}" class="nav-logo" style="display:inline-block; font-size:1.75rem; margin-bottom:1rem;" aria-label="hoaxlin.id beranda">hoaxlin.id</a>
                <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:0.375rem;">Buat Akun Gratis</h1>
                <p style="color:var(--color-text-muted); font-size:0.9375rem;">Bergabung dan mulai cek berita lebih cerdas.</p>
            </div>

            <!-- Benefits Pills -->
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem; justify-content:center; margin-bottom:2rem;" aria-label="Manfaat mendaftar">
                @foreach(['🕐 Simpan Riwayat', '📊 Lihat Statistik', '💬 Beri Umpan Balik', '🔔 Notifikasi Hasil'] as $benefit)
                <span style="padding:0.25rem 0.75rem; background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.15); border-radius:2rem; color:var(--color-text-secondary); font-size:0.78125rem;">
                    {{ $benefit }}
                </span>
                @endforeach
            </div>

            <!-- Validation Errors -->
            @if($errors->any())
            <div style="padding:0.875rem 1rem; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); border-radius:0.75rem; margin-bottom:1.5rem;" role="alert">
                <ul style="list-style:none; display:flex; flex-direction:column; gap:0.25rem;">
                    @foreach($errors->all() as $error)
                    <li style="color:#f87171; font-size:0.875rem;">• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Register Form -->
            <form method="POST" action="{{ route('register.store') }}" id="register-form" novalidate>
                @csrf

                <!-- Name -->
                <div style="margin-bottom:1.125rem;">
                    <label for="name" class="auth-label">Nama Lengkap</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="auth-input"
                        value="{{ old('name') }}"
                        required
                        autocomplete="name"
                        autofocus
                        placeholder="Nama Kamu"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                    >
                    @error('name')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div style="margin-bottom:1.125rem;">
                    <label for="email" class="auth-label">Alamat Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="auth-input"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        placeholder="kamu@email.com"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    >
                    @error('email')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div style="margin-bottom:1.125rem;">
                    <label for="password" class="auth-label">Kata Sandi</label>
                    <div style="position:relative;">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="auth-input"
                            required
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            style="padding-right:3rem;"
                            oninput="checkPasswordStrength()"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            aria-describedby="password-strength"
                        >
                        <button type="button" onclick="togglePass('password')"
                                style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;"
                                aria-label="Tampilkan/sembunyikan kata sandi">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>

                    <!-- Password Strength Indicator -->
                    <div id="password-strength" style="margin-top:0.5rem; display:none;" aria-live="polite">
                        <div style="display:flex; gap:0.25rem; margin-bottom:0.375rem;">
                            <div id="ps-1" style="height:3px; flex:1; border-radius:2px; background:rgba(100,116,139,0.2); transition:background 0.3s;"></div>
                            <div id="ps-2" style="height:3px; flex:1; border-radius:2px; background:rgba(100,116,139,0.2); transition:background 0.3s;"></div>
                            <div id="ps-3" style="height:3px; flex:1; border-radius:2px; background:rgba(100,116,139,0.2); transition:background 0.3s;"></div>
                            <div id="ps-4" style="height:3px; flex:1; border-radius:2px; background:rgba(100,116,139,0.2); transition:background 0.3s;"></div>
                        </div>
                        <span id="ps-label" style="font-size:0.75rem; color:var(--color-text-muted);"></span>
                    </div>

                    @error('password')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div style="margin-bottom:1.5rem;">
                    <label for="password_confirmation" class="auth-label">Konfirmasi Kata Sandi</label>
                    <div style="position:relative;">
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="auth-input"
                            required
                            autocomplete="new-password"
                            placeholder="Ulangi kata sandi"
                            style="padding-right:3rem;"
                            oninput="checkPasswordMatch()"
                            aria-describedby="confirm-match"
                        >
                        <button type="button" onclick="togglePass('password_confirmation')"
                                style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;"
                                aria-label="Tampilkan/sembunyikan konfirmasi kata sandi">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <p id="confirm-match" style="font-size:0.75rem; margin-top:0.375rem; display:none;" aria-live="polite"></p>
                </div>

                <!-- Terms -->
                <div style="display:flex; align-items:flex-start; gap:0.625rem; margin-bottom:1.5rem;">
                    <input type="checkbox" id="terms" name="terms" required
                           style="width:1rem; height:1rem; accent-color:var(--color-primary); cursor:pointer; margin-top:2px; flex-shrink:0;">
                    <label for="terms" style="color:var(--color-text-secondary); font-size:0.875rem; cursor:pointer; line-height:1.5;">
                        Saya menyetujui
                        <a href="{{ url('/kebijakan-privasi') }}" style="color:var(--color-primary-light); text-decoration:none; font-weight:500;">Kebijakan Privasi</a>
                        dan memahami bahwa hasil deteksi bersifat indikatif.
                    </label>
                </div>
                @error('terms')
                    <p class="auth-error" role="alert" style="margin-top:-1rem; margin-bottom:1rem;">{{ $message }}</p>
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
        </div>

        <p style="text-align:center; color:var(--color-text-muted); font-size:0.8125rem; margin-top:1.25rem; display:flex; align-items:center; justify-content:center; gap:0.375rem;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Koneksi aman & terenkripsi · Tidak ada spam
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePass(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function checkPasswordStrength() {
    const pass = document.getElementById('password').value;
    const indicator = document.getElementById('password-strength');

    if (pass.length === 0) { indicator.style.display = 'none'; return; }
    indicator.style.display = 'block';

    let score = 0;
    if (pass.length >= 8) score++;
    if (pass.length >= 12) score++;
    if (/[A-Z]/.test(pass) && /[a-z]/.test(pass)) score++;
    if (/[0-9]/.test(pass) && /[^A-Za-z0-9]/.test(pass)) score++;

    const colors = ['#f87171', '#fbbf24', '#60a5fa', '#34d399'];
    const labels = ['Lemah', 'Sedang', 'Kuat', 'Sangat Kuat'];

    for (let i = 1; i <= 4; i++) {
        const el = document.getElementById(`ps-${i}`);
        el.style.background = i <= score ? colors[score - 1] : 'rgba(100,116,139,0.2)';
    }
    document.getElementById('ps-label').textContent = labels[score - 1] || '';
    document.getElementById('ps-label').style.color = colors[score - 1] || 'var(--color-text-muted)';
}

function checkPasswordMatch() {
    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirmation').value;
    const el = document.getElementById('confirm-match');

    if (confirm.length === 0) { el.style.display = 'none'; return; }
    el.style.display = 'block';

    if (pass === confirm) {
        el.textContent = '✓ Kata sandi cocok';
        el.style.color = '#34d399';
    } else {
        el.textContent = '✗ Kata sandi tidak cocok';
        el.style.color = '#f87171';
    }
}

// Prevent double submit
document.getElementById('register-form').addEventListener('submit', (e) => {
    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirmation').value;
    if (pass !== confirm) {
        e.preventDefault();
        document.getElementById('confirm-match').style.display = 'block';
        document.getElementById('confirm-match').textContent = '✗ Kata sandi tidak cocok';
        document.getElementById('confirm-match').style.color = '#f87171';
        return;
    }
    const btn = document.getElementById('register-btn');
    btn.disabled = true;
    btn.innerHTML = '<span>Membuat akun...</span><div class="spinner" style="width:1rem;height:1rem;border-width:2px;" aria-hidden="true"></div>';
});
</script>
@endpush
