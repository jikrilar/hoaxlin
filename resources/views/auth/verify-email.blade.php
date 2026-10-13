@extends('layouts.app')

@section('title', 'Verifikasi Email — hoaxlin.id')

@section('content')
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:6rem 1rem 3rem;">

    <div style="width:100%; max-width:480px; position:relative;">
        <div class="glass-card-solid" style="padding:clamp(var(--ds-space-4), 4vw, 2.75rem); text-align:center;">
            <div style="font-size:2.5rem; margin-bottom:1rem;" aria-hidden="true">✉️</div>
            <h1 style="font-size:1.5rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.75rem;">Verifikasi Alamat Email</h1>
            <p style="color:var(--color-text-muted); line-height:1.7; margin-bottom:1.5rem;">
                Tautan verifikasi telah dikirim ke <strong style="color:var(--color-text-secondary);">{{ auth()->user()->email }}</strong>. Verifikasi email untuk mengakses riwayat dan mengirim umpan balik.
                Jika belum terlihat, periksa kotak masuk dan folder spam, atau minta tautan baru di bawah.
            </p>

            <form method="POST" action="{{ route('verification.send') }}" style="margin-bottom:1rem;">
                @csrf
                <x-ui.button type="submit" class="w-full">Kirim Ulang Email Verifikasi</x-ui.button>
            </form>

            <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
                <x-ui.link :href="route('profile')" variant="tertiary">Ubah Email</x-ui.link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.button type="submit" variant="tertiary">Keluar</x-ui.button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
