@extends('layouts.auth')

@section('title', 'Verifikasi Email — hoaxlin.id')

@section('content')
<div class="hl-auth-title">
    <h1>Verifikasi Alamat Email</h1>
    <p>
        Tautan verifikasi telah dikirim ke <strong>{{ auth()->user()->email }}</strong>. Verifikasi email untuk mengakses riwayat dan mengirim umpan balik.
        Jika belum terlihat, periksa kotak masuk dan folder spam, atau minta tautan baru di bawah.
    </p>
</div>

<form method="POST" action="{{ route('verification.send') }}" data-submit-label="Mengirim..." style="margin-bottom:1rem;">
    @csrf
    <x-ui.button type="submit" class="w-full">Kirim Ulang Email Verifikasi</x-ui.button>
</form>

<div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
    <x-ui.link :href="route('profile')" variant="tertiary">Ubah Email</x-ui.link>
    <form method="POST" action="{{ route('logout') }}" data-submit-label="Keluar...">
        @csrf
        <x-ui.button type="submit" variant="tertiary">Keluar</x-ui.button>
    </form>
</div>
@endsection
