@extends('layouts.app')

@section('title', 'Verifikasi Email — hoaxlin.id')

@section('content')
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:6rem 1rem 3rem;">
    <div class="glow-orb glow-orb-1" style="opacity:0.5;" aria-hidden="true"></div>
    <div style="width:100%; max-width:480px; position:relative;" class="animate-scale-in">
        <div class="glass-card-solid" style="padding:2.75rem; text-align:center;" role="main">
            <div style="font-size:2.5rem; margin-bottom:1rem;" aria-hidden="true">✉️</div>
            <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:0.75rem;">Verifikasi Alamat Email</h1>
            <p style="color:var(--color-text-muted); line-height:1.7; margin-bottom:1.5rem;">
                Tautan verifikasi telah dikirim ke <strong style="color:var(--color-text-secondary);">{{ auth()->user()->email }}</strong>. Verifikasi email untuk mengakses riwayat dan mengirim umpan balik.
            </p>

            @if (session('status') === 'verification-link-sent')
                <div style="padding:0.875rem; background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); border-radius:0.75rem; color:#34d399; font-size:0.875rem; margin-bottom:1.25rem;" role="status">
                    Tautan verifikasi baru telah dikirim.
                </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}" style="margin-bottom:1rem;">
                @csrf
                <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">Kirim Ulang Email Verifikasi</button>
            </form>

            <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
                <a href="{{ route('profile') }}" class="btn-ghost">Ubah Email</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
