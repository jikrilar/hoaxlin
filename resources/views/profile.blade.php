@extends('layouts.app')

@section('title', 'Profil Saya — hoaxlin.id')
@section('description', 'Kelola informasi akun dan preferensi hoaxlin.id kamu.')

@section('content')
<div style="padding:9rem 0 5rem; min-height:100vh;">
    <div class="container-app" style="max-width:800px;">

        <!-- Page Header -->
        <div style="margin-bottom:2.5rem;" class="animate-fade-in-up">
            <h1 style="font-size:2rem; font-weight:800; margin-bottom:0.5rem;">
                Profil <span class="gradient-text">Saya</span>
            </h1>
            <p style="color:var(--color-text-secondary);">
                Kelola informasi akun dan keamanan akun kamu.
            </p>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
        <div style="padding:1rem 1.25rem; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); border-radius:0.875rem; margin-bottom:1.5rem; display:flex; gap:0.625rem; align-items:center; animation:fadeInUp 0.4s ease;" role="status">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p style="color:#34d399; font-size:0.875rem;">{{ session('success') }}</p>
        </div>
        @endif

        @if($errors->any())
        <div style="padding:0.875rem 1rem; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); border-radius:0.75rem; margin-bottom:1.5rem;" role="alert">
            @foreach($errors->all() as $error)
            <p style="color:#f87171; font-size:0.875rem;">{{ $error }}</p>
            @endforeach
        </div>
        @endif

        <!-- Avatar & Quick Info -->
        <div class="glass-card animate-fade-in-up" style="padding:2rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1.75rem; flex-wrap:wrap;">
            <!-- Avatar -->
            <div style="width:5rem; height:5rem; border-radius:50%; background:linear-gradient(135deg, #6366f1, #06b6d4); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-family:var(--font-display); font-size:1.75rem; font-weight:800; color:white; box-shadow:0 4px 20px rgba(99,102,241,0.4);" aria-hidden="true">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div style="flex:1; min-width:0;">
                <h2 style="font-size:1.375rem; font-weight:700; margin-bottom:0.25rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ auth()->user()->name }}
                </h2>
                <p style="color:var(--color-text-muted); font-size:0.9375rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ auth()->user()->email }}
                </p>
                <p style="color:var(--color-text-muted); font-size:0.8125rem; margin-top:0.375rem;">
                    Bergabung sejak {{ auth()->user()->created_at->format('d M Y') }}
                </p>
                <p style="font-size:0.8125rem; margin-top:0.375rem; color:{{ auth()->user()->hasVerifiedEmail() ? '#34d399' : '#fbbf24' }};">
                    {{ auth()->user()->hasVerifiedEmail() ? 'Email terverifikasi' : 'Email belum terverifikasi' }}
                </p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a href="{{ url('/riwayat') }}" class="btn-secondary" style="font-size:0.875rem; padding:0.625rem 1.25rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Saya
                </a>
            </div>
        </div>

        <!-- Update Profile Form -->
        <div class="glass-card animate-fade-in-up delay-100" style="padding:2rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1.125rem; font-weight:700; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                <span style="font-size:1.125rem;" aria-hidden="true">👤</span>
                Informasi Profil
            </h2>

            <form method="POST" action="{{ route('profile.update') }}" id="profile-form" novalidate>
                @csrf
                @method('PATCH')

                <!-- Name -->
                <div style="margin-bottom:1.25rem;">
                    <label for="name" class="auth-label">Nama Lengkap</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="auth-input"
                        value="{{ old('name', auth()->user()->name) }}"
                        required
                        autocomplete="name"
                        placeholder="Nama lengkap kamu"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                    >
                    @error('name')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div style="margin-bottom:1.75rem;">
                    <label for="email" class="auth-label">Alamat Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="auth-input"
                        value="{{ old('email', auth()->user()->email) }}"
                        required
                        autocomplete="email"
                        placeholder="kamu@email.com"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                    >
                    @error('email')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-primary" id="save-profile-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v14a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Simpan Perubahan</span>
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="glass-card animate-fade-in-up delay-200" style="padding:2rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1.125rem; font-weight:700; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                <span style="font-size:1.125rem;" aria-hidden="true">🔒</span>
                Ubah Kata Sandi
            </h2>

            <form method="POST" action="{{ route('password.update') }}" id="password-form" novalidate>
                @csrf
                @method('PUT')

                <!-- Current Password -->
                <div style="margin-bottom:1.25rem;">
                    <label for="current_password" class="auth-label">Kata Sandi Saat Ini</label>
                    <div style="position:relative;">
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="auth-input"
                            required
                            autocomplete="current-password"
                            placeholder="Kata sandi kamu sekarang"
                            style="padding-right:3rem;"
                            aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}"
                        >
                        <button type="button" onclick="togglePwd('current_password', this)" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;" aria-label="Tampilkan/sembunyikan">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('current_password')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <!-- New Password -->
                <div style="margin-bottom:1.25rem;">
                    <label for="password" class="auth-label">Kata Sandi Baru</label>
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
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        >
                        <button type="button" onclick="togglePwd('password', this)" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;" aria-label="Tampilkan/sembunyikan">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror

                    <!-- Password strength bar -->
                    <div style="margin-top:0.75rem;" id="strength-container" style="display:none;">
                        <div style="display:flex; gap:0.25rem; margin-bottom:0.25rem;">
                            <div id="strength-bar-1" style="flex:1; height:3px; border-radius:2px; background:rgba(148,163,184,0.15); transition:background 0.3s;"></div>
                            <div id="strength-bar-2" style="flex:1; height:3px; border-radius:2px; background:rgba(148,163,184,0.15); transition:background 0.3s;"></div>
                            <div id="strength-bar-3" style="flex:1; height:3px; border-radius:2px; background:rgba(148,163,184,0.15); transition:background 0.3s;"></div>
                            <div id="strength-bar-4" style="flex:1; height:3px; border-radius:2px; background:rgba(148,163,184,0.15); transition:background 0.3s;"></div>
                        </div>
                        <p id="strength-label" style="color:var(--color-text-muted); font-size:0.75rem;" aria-live="polite"></p>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div style="margin-bottom:1.75rem;">
                    <label for="password_confirmation" class="auth-label">Konfirmasi Kata Sandi Baru</label>
                    <div style="position:relative;">
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="auth-input"
                            required
                            autocomplete="new-password"
                            placeholder="Ulangi kata sandi baru"
                            style="padding-right:3rem;"
                        >
                        <button type="button" onclick="togglePwd('password_confirmation', this)" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;" aria-label="Tampilkan/sembunyikan">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="save-password-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Perbarui Kata Sandi</span>
                </button>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="glass-card animate-fade-in-up delay-300" style="padding:2rem; border-color:rgba(239,68,68,0.2);" role="region" aria-label="Zona bahaya">
            <h2 style="font-size:1.125rem; font-weight:700; margin-bottom:0.5rem; color:#f87171; display:flex; align-items:center; gap:0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Zona Bahaya
            </h2>
            <p style="color:var(--color-text-muted); font-size:0.875rem; margin-bottom:1.25rem; line-height:1.6;">
                Menghapus akun bersifat permanen dan tidak dapat dibatalkan. Seluruh riwayat pengecekan dan data kamu akan dihapus selamanya.
            </p>
            <button type="button"
                    onclick="document.getElementById('delete-modal').style.display='flex'"
                    style="display:inline-flex; align-items:center; gap:0.5rem; padding:0.625rem 1.25rem; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); border-radius:0.75rem; color:#f87171; font-size:0.875rem; font-weight:500; cursor:pointer; transition:all 0.2s ease;"
                    onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'"
                    id="delete-account-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                Hapus Akun Saya
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" style="display:none; position:fixed; inset:0; z-index:200; background:rgba(8,11,20,0.85); backdrop-filter:blur(8px); align-items:center; justify-content:center; padding:1rem;" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
    <div class="glass-card-solid" style="padding:2rem; max-width:420px; width:100%; animation:scaleIn 0.3s ease;">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div style="font-size:2.5rem; margin-bottom:0.75rem;" aria-hidden="true">⚠️</div>
            <h3 id="delete-modal-title" style="font-size:1.25rem; font-weight:700; margin-bottom:0.5rem;">Hapus Akun Secara Permanen?</h3>
            <p style="color:var(--color-text-muted); font-size:0.9rem; line-height:1.6;">
                Tindakan ini tidak dapat dibatalkan. Seluruh data kamu, termasuk riwayat pengecekan, akan dihapus secara permanen.
            </p>
        </div>
        <form method="POST" action="{{ route('profile.destroy') }}">
            @csrf
            @method('DELETE')
            <div style="margin-bottom:1.25rem;">
                <label for="confirm-password" class="auth-label">Konfirmasi dengan Kata Sandi</label>
                <input type="password" id="confirm-password" name="password" class="auth-input" placeholder="Kata sandi kamu" required>
            </div>
            <div style="display:flex; gap:0.75rem;">
                <button type="button" onclick="document.getElementById('delete-modal').style.display='none'" class="btn-secondary" style="flex:1; justify-content:center;">
                    Batal
                </button>
                <button type="submit" style="flex:1; display:flex; align-items:center; justify-content:center; gap:0.5rem; padding:0.75rem 1rem; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.4); border-radius:0.75rem; color:#f87171; font-weight:600; cursor:pointer; transition:all 0.2s; font-size:0.9375rem;"
                        onmouseover="this.style.background='rgba(239,68,68,0.25)'" onmouseout="this.style.background='rgba(239,68,68,0.15)'">
                    Ya, Hapus Akun
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePwd(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelector('svg').innerHTML = isHidden
        ? '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}

// Password strength indicator
const pwdInput = document.getElementById('password');
const bars = [1,2,3,4].map(i => document.getElementById('strength-bar-' + i));
const strengthLabel = document.getElementById('strength-label');

if (pwdInput) {
    pwdInput.addEventListener('input', () => {
        const val = pwdInput.value;
        document.getElementById('strength-container').style.display = val ? 'block' : 'none';
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const colors = ['#f87171', '#fbbf24', '#fbbf24', '#34d399'];
        const labels = ['Lemah', 'Cukup', 'Kuat', 'Sangat Kuat'];
        bars.forEach((bar, i) => {
            bar.style.background = i < score ? colors[score - 1] : 'rgba(148,163,184,0.15)';
        });
        strengthLabel.textContent = score > 0 ? labels[score - 1] : '';
        strengthLabel.style.color = score > 0 ? colors[score - 1] : 'var(--color-text-muted)';
    });
}

// Prevent double submit
document.getElementById('profile-form')?.addEventListener('submit', (e) => {
    const btn = document.getElementById('save-profile-btn');
    btn.disabled = true;
    btn.innerHTML = '<span>Menyimpan...</span><div class="spinner" style="width:1rem;height:1rem;border-width:2px;" aria-hidden="true"></div>';
});

document.getElementById('password-form')?.addEventListener('submit', (e) => {
    const btn = document.getElementById('save-password-btn');
    btn.disabled = true;
    btn.innerHTML = '<span>Memperbarui...</span><div class="spinner" style="width:1rem;height:1rem;border-width:2px;" aria-hidden="true"></div>';
});

// Close modal on backdrop click
document.getElementById('delete-modal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('delete-modal')) {
        document.getElementById('delete-modal').style.display = 'none';
    }
});
</script>
@endpush
