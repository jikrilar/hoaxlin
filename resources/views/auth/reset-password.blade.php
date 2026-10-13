@extends('layouts.app')

@section('title', 'Reset Kata Sandi — hoaxlin.id')

@section('content')
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:6rem 1rem 3rem;">

    <div style="width:100%; max-width:440px; position:relative;">
        <div class="glass-card-solid" style="padding:clamp(var(--ds-space-4), 4vw, 2.75rem);">
            <div style="text-align:center; margin-bottom:2rem;">
                <a href="{{ route('home') }}" class="nav-logo" style="display:inline-block; font-size:1.75rem; margin-bottom:1rem;">hoaxlin.id</a>
                <h1 style="font-size:1.5rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.375rem;">Buat Kata Sandi Baru</h1>
                <p style="color:var(--color-text-muted); font-size:0.9375rem;">Gunakan kata sandi baru untuk akun kamu.</p>
            </div>

            <form method="POST" action="{{ route('password.store') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <x-ui.field id="email" label="Alamat Email" :error="$errors->first('email')">
                    <x-forms.input type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="email" />
                </x-ui.field>

                <x-ui.field id="password" label="Kata Sandi Baru" :error="$errors->first('password')">
                    <x-forms.input type="password" name="password" required autocomplete="new-password" />
                </x-ui.field>

                <x-ui.field id="password_confirmation" label="Konfirmasi Kata Sandi" :error="$errors->first('password_confirmation')">
                    <x-forms.input type="password" name="password_confirmation" required autocomplete="new-password" />
                </x-ui.field>

                <x-ui.button type="submit" class="w-full">Reset Kata Sandi</x-ui.button>
            </form>
        </div>
    </div>
</div>
@endsection
