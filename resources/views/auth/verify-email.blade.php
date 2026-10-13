@extends('layouts.auth')

@section('title', 'Verifikasi email — hoaxlin.id')
@section('description', 'Verifikasi email akun hoaxlin.id untuk mengakses riwayat dan mengirim umpan balik.')

@section('content')
<div class="hl-auth-title">
    <h1>Verifikasi email</h1>
    <p>Verifikasi email untuk mengakses riwayat dan mengirim umpan balik.</p>
    <p>Alamat email akun: <strong>{{ auth()->user()->email }}</strong></p>
    <p>Buka tautan verifikasi dari email Anda. Periksa kotak masuk dan folder spam. Jika tautan belum terlihat atau sudah kedaluwarsa, minta tautan baru.</p>
</div>

<form method="POST" action="{{ route('verification.send') }}" id="verification-resend-form" class="hl-auth-form" data-submit-label="Mengirim...">
    @csrf
    <x-ui.button type="submit" id="verification-resend-submit">Kirim ulang tautan</x-ui.button>
</form>

<div class="hl-auth-options hl-auth-switch">
    <x-ui.link :href="route('profile')" variant="tertiary">Ubah email</x-ui.link>
    <form method="POST" action="{{ route('logout') }}" id="verification-logout-form" data-submit-label="Keluar...">
        @csrf
        <x-ui.button type="submit" variant="tertiary">Keluar</x-ui.button>
    </form>
</div>
@endsection
