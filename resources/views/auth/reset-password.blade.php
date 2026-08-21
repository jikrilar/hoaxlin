@extends('layouts.app')

@section('title', 'Reset Kata Sandi — hoaxlin.id')

@section('content')
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:6rem 1rem 3rem;">
    <div class="glow-orb glow-orb-1" style="opacity:0.5;" aria-hidden="true"></div>
    <div style="width:100%; max-width:440px; position:relative;" class="animate-scale-in">
        <div class="glass-card-solid" style="padding:2.75rem;" role="main">
            <div style="text-align:center; margin-bottom:2rem;">
                <a href="{{ route('home') }}" class="nav-logo" style="display:inline-block; font-size:1.75rem; margin-bottom:1rem;">hoaxlin.id</a>
                <h1 style="font-size:1.5rem; font-weight:700; margin-bottom:0.375rem;">Buat Kata Sandi Baru</h1>
                <p style="color:var(--color-text-muted); font-size:0.9375rem;">Gunakan kata sandi baru untuk akun kamu.</p>
            </div>

            <form method="POST" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div style="margin-bottom:1.25rem;">
                    <label for="email" class="auth-label">Alamat Email</label>
                    <input id="email" class="auth-input" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email">
                    @error('email')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label for="password" class="auth-label">Kata Sandi Baru</label>
                    <input id="password" class="auth-input" type="password" name="password" required autocomplete="new-password">
                    @error('password')<p class="auth-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label for="password_confirmation" class="auth-label">Konfirmasi Kata Sandi</label>
                    <input id="password_confirmation" class="auth-input" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">Reset Kata Sandi</button>
            </form>
        </div>
    </div>
</div>
@endsection
