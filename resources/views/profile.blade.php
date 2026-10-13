@extends('layouts.app')

@section('title', 'Profil Saya — hoaxlin.id')
@section('description', 'Kelola informasi akun dan preferensi hoaxlin.id kamu.')

@section('content')
<div style="padding:9rem 0 5rem; min-height:100vh;">
    <div class="container-app" style="max-width:800px;">

        <!-- Page Header -->
        <div style="margin-bottom:2.5rem;">
            <h1 style="font-size:2rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.5rem;">
                Profil <span class="gradient-text">Saya</span>
            </h1>
            <p style="color:var(--color-text-secondary);">
                Kelola informasi akun dan keamanan akun kamu.
            </p>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
        <div style="padding:1rem 1.25rem; background:var(--ds-success-bg); border:1px solid var(--ds-success-fg); border-radius:0.875rem; margin-bottom:1.5rem; display:flex; gap:0.625rem; align-items:center; " role="status">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ds-success-fg)" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p style="color:var(--ds-success-fg); font-size:0.875rem;">{{ session('success') }}</p>
        </div>
        @endif

        @if($errors->any())
        <div style="padding:0.875rem 1rem; background:var(--ds-danger-bg); border:1px solid var(--ds-danger-fg); border-radius:0.75rem; margin-bottom:1.5rem;" role="alert">
            @foreach($errors->all() as $error)
            <p style="color:var(--ds-danger-fg); font-size:0.875rem;">{{ $error }}</p>
            @endforeach
        </div>
        @endif

        <!-- Avatar & Quick Info -->
        <div class="glass-card" style="padding:2rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:1.75rem; flex-wrap:wrap;">
            <!-- Avatar -->
            <div style="width:5rem; height:5rem; border-radius:50%; background:var(--ds-action-primary); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-family:var(--font-display); font-size:1.75rem; font-weight:var(--ds-font-weight-semibold); color:var(--ds-text-inverse); box-shadow:0 4px 20px var(--ds-bg-selected);" aria-hidden="true">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div style="flex:1; min-width:0;">
                <h2 style="font-size:1.375rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.25rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ auth()->user()->name }}
                </h2>
                <p style="color:var(--color-text-muted); font-size:0.9375rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ auth()->user()->email }}
                </p>
                <p style="color:var(--color-text-muted); font-size:0.8125rem; margin-top:0.375rem;">
                    Bergabung sejak {{ auth()->user()->created_at->format('d M Y') }}
                </p>
                <p style="font-size:0.8125rem; margin-top:0.375rem; color:{{ auth()->user()->hasVerifiedEmail() ? 'var(--ds-success-fg)' : 'var(--ds-warning-fg)' }};">
                    {{ auth()->user()->hasVerifiedEmail() ? 'Email terverifikasi' : 'Email belum terverifikasi' }}
                </p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <x-ui.link :href="url('/riwayat')" variant="secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Saya
                </x-ui.link>
            </div>
        </div>

        <!-- Update Profile Form -->
        <div class="glass-card" style="padding:2rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1.125rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                <span style="font-size:1.125rem;" aria-hidden="true">👤</span>
                Informasi Profil
            </h2>

            <form method="POST" action="{{ route('profile.update') }}" id="profile-form" data-submit-label="Menyimpan..." novalidate>
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
        <div class="glass-card" style="padding:2rem; margin-bottom:1.5rem;">
            <h2 style="font-size:1.125rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:1.5rem; display:flex; align-items:center; gap:0.5rem;">
                <span style="font-size:1.125rem;" aria-hidden="true">🔒</span>
                Ubah Kata Sandi
            </h2>

            <form method="POST" action="{{ route('password.update') }}" id="password-form" data-submit-label="Memperbarui..." data-password-hints="profile" novalidate>
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
                        <button type="button" data-password-toggle="current_password" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;" aria-label="Tampilkan/sembunyikan">
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
                        <button type="button" data-password-toggle="password" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;" aria-label="Tampilkan/sembunyikan">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror

                    <!-- Password strength bar -->
                    <div id="strength-container" style="margin-top:0.75rem; display:none;">
                        <div style="display:flex; gap:0.25rem; margin-bottom:0.25rem;">
                            <div id="strength-bar-1" style="flex:1; height:3px; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
                            <div id="strength-bar-2" style="flex:1; height:3px; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
                            <div id="strength-bar-3" style="flex:1; height:3px; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
                            <div id="strength-bar-4" style="flex:1; height:3px; border-radius:2px; background:var(--ds-bg-subtle); transition:background 0.3s;"></div>
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
                        <button type="button" data-password-toggle="password_confirmation" style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--color-text-muted); cursor:pointer;" aria-label="Tampilkan/sembunyikan">
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
        <div class="glass-card" style="padding:2rem; border-color:var(--ds-danger-fg);" role="region" aria-label="Zona bahaya">
            <h2 style="font-size:1.125rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.5rem; color:var(--ds-danger-fg); display:flex; align-items:center; gap:0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Zona Bahaya
            </h2>
            <p style="color:var(--color-text-muted); font-size:0.875rem; margin-bottom:1.25rem; line-height:1.6;">
                Menghapus akun bersifat permanen dan tidak dapat dibatalkan. Seluruh riwayat pengecekan dan data kamu akan dihapus selamanya.
            </p>
            <button type="button"
                    data-dialog-open="delete-modal"
                   style="display:inline-flex; align-items:center; gap:0.5rem; padding:0.625rem 1.25rem; background:var(--ds-danger-bg); border:1px solid var(--ds-danger-fg); border-radius:0.75rem; color:var(--ds-danger-fg); font-size:0.875rem; font-weight:500; cursor:pointer; transition:all 0.2s ease;"
                    onmouseover="this.style.background='var(--ds-danger-bg)'" onmouseout="this.style.background='var(--ds-danger-bg)'"
                    id="delete-account-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                Hapus Akun Saya
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<dialog id="delete-modal" data-dialog class="hl-dialog" aria-labelledby="delete-modal-title">
    <div class="glass-card-solid" style="padding:2rem; max-width:420px; width:100%; animation:scaleIn 0.3s ease;">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div style="font-size:2.5rem; margin-bottom:0.75rem;" aria-hidden="true">⚠️</div>
            <h3 id="delete-modal-title" style="font-size:1.25rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.5rem;">Hapus Akun Secara Permanen?</h3>
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
                <x-ui.button variant="secondary" class="flex-1" data-dialog-close autofocus>
                    Batal
                </x-ui.button>
                <x-ui.button type="submit" variant="destructive" class="flex-1">
                    Ya, Hapus Akun
                </x-ui.button>
            </div>
        </form>
    </div>
</dialog>
@endsection
