@extends('layouts.app')

@section('title', 'Profil dan keamanan akun — hoaxlin.id')
@section('description', 'Kelola identitas, verifikasi email, kata sandi, dan penghapusan akun hoaxlin.id.')

@php
    $user = auth()->user();
    $profileErrors = $errors->getBag('profile');
    $passwordErrors = $errors->getBag('passwordUpdate');
    $deleteErrors = $errors->getBag('accountDeletion');
    $profileName = old('name', $user->name);
    $profileEmail = old('email', $user->email);
@endphp

@section('content')
<div class="container-app hl-profile-page">
    <header class="hl-profile-intro">
        <h1>Profil dan keamanan akun</h1>
        <p>Kelola identitas dan keamanan akun Anda.</p>
        <div class="hl-profile-status">
            <span>{{ $user->hasVerifiedEmail() ? 'Email terverifikasi' : 'Email belum terverifikasi' }}</span>
            @if($user->hasVerifiedEmail())
                <x-ui.link :href="route('riwayat')" variant="tertiary">Lihat riwayat</x-ui.link>
            @else
                <x-ui.link :href="route('verification.notice')" variant="tertiary">Verifikasi email</x-ui.link>
            @endif
        </div>
    </header>

    <section class="hl-profile-section" aria-labelledby="profile-identity-title">
        <h2 id="profile-identity-title">Identitas akun</h2>
        <p>Mengubah alamat email akan meminta verifikasi ulang. Akun yang belum terverifikasi akan diarahkan ke halaman verifikasi setelah disimpan.</p>
        @include('partials.profile.errors', ['bag' => $profileErrors, 'id' => 'profile-errors', 'title' => 'Periksa identitas akun', 'targets' => ['name' => 'name', 'email' => 'email'], 'focus' => ! $deleteErrors->any()])
        <form method="POST" action="{{ route('profile.update') }}" id="profile-form" data-submit-label="Menyimpan...">
            @csrf
            @method('PATCH')
            <x-ui.field id="name" label="Nama lengkap" :error="$profileErrors->first('name')">
                <x-forms.input name="name" :value="is_string($profileName) ? $profileName : ''" required maxlength="255" autocomplete="name" />
            </x-ui.field>
            <x-ui.field id="email" label="Alamat email" hint="Gunakan alamat email yang dapat Anda akses." :error="$profileErrors->first('email')">
                <x-forms.input type="email" name="email" :value="is_string($profileEmail) ? $profileEmail : ''" required maxlength="255" autocomplete="email" autocapitalize="none" spellcheck="false" />
            </x-ui.field>
            <x-ui.button type="submit" id="save-profile-btn">Simpan identitas</x-ui.button>
        </form>
    </section>

    <section class="hl-profile-section" aria-labelledby="profile-password-title">
        <h2 id="profile-password-title">Ubah kata sandi</h2>
        <p>Masukkan kata sandi saat ini untuk menetapkan kata sandi baru.</p>
        @include('partials.profile.errors', ['bag' => $passwordErrors, 'id' => 'password-errors', 'title' => 'Periksa perubahan kata sandi', 'targets' => ['current_password' => 'current_password', 'password' => 'password', 'password_confirmation' => 'password_confirmation'], 'focus' => ! $deleteErrors->any() && ! $profileErrors->any()])
        <form method="POST" action="{{ route('password.update') }}" id="password-form" data-submit-label="Memperbarui...">
            @csrf
            @method('PUT')
            <x-ui.field id="current_password" label="Kata sandi saat ini" :error="$passwordErrors->first('current_password')">
                <x-forms.password name="current_password" required autocomplete="current-password" />
            </x-ui.field>
            <x-ui.field id="password" label="Kata sandi baru" hint="Gunakan kata sandi yang unik untuk akun ini." :error="$passwordErrors->first('password')">
                <x-forms.password name="password" required autocomplete="new-password" />
            </x-ui.field>
            <x-ui.field id="password_confirmation" label="Konfirmasi kata sandi baru" hint="Masukkan kembali kata sandi yang sama." :error="$passwordErrors->first('password_confirmation')">
                <x-forms.password name="password_confirmation" required autocomplete="new-password" />
            </x-ui.field>
            <x-ui.button type="submit" id="save-password-btn">Perbarui kata sandi</x-ui.button>
        </form>
    </section>

    <section class="hl-profile-section hl-profile-delete" aria-labelledby="profile-delete-title">
        <h2 id="profile-delete-title">Hapus akun</h2>
        <p>Penghapusan akun bersifat permanen. Akun, riwayat pemeriksaan milik akun, dan media unggahannya akan dihapus. Anda akan keluar dari akun.</p>
        <x-ui.button variant="destructive" id="delete-account-btn" data-dialog-open="delete-modal" aria-haspopup="dialog" aria-controls="delete-modal" hidden>Hapus akun</x-ui.button>
        <details id="delete-confirmation" @if($deleteErrors->any()) open @endif>
            <summary class="hl-button" data-variant="destructive">Konfirmasi penghapusan akun</summary>
            <div class="hl-profile-confirmation" data-dialog-panel>
                <h3 id="delete-modal-title">Hapus akun secara permanen?</h3>
                <p id="delete-modal-description">Tindakan ini tidak dapat dibatalkan. Masukkan kata sandi akun untuk mengonfirmasi penghapusan.</p>
                @include('partials.profile.errors', ['bag' => $deleteErrors, 'id' => 'delete-errors', 'title' => 'Akun belum dihapus', 'targets' => ['password' => 'confirm-password'], 'focus' => true])
                <form method="POST" action="{{ route('profile.destroy') }}" id="delete-account-form" data-submit-label="Menghapus...">
                    @csrf
                    @method('DELETE')
                    <x-ui.field id="confirm-password" label="Kata sandi akun" :error="$deleteErrors->first('password')">
                        <x-forms.password name="password" required autocomplete="current-password" />
                    </x-ui.field>
                    <div class="hl-profile-actions">
                        <x-ui.button variant="secondary" id="cancel-delete-btn" data-dialog-close hidden :autofocus="! $deleteErrors->any()">Batal</x-ui.button>
                        <x-ui.button type="submit" variant="destructive" id="confirm-delete-btn">Hapus akun secara permanen</x-ui.button>
                    </div>
                </form>
            </div>
        </details>
        <dialog id="delete-modal" class="hl-dialog hl-profile-dialog" data-dialog data-dialog-fallback="delete-confirmation" data-dialog-reset-on-close
            @if($deleteErrors->any()) data-dialog-auto-open @endif aria-labelledby="delete-modal-title" aria-describedby="delete-modal-description"></dialog>
    </section>
</div>
@endsection
