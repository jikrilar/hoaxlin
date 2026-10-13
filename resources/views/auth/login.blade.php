@extends('layouts.auth')

@section('title', 'Masuk — hoaxlin.id')
@section('description', 'Masuk ke akun hoaxlin.id untuk melanjutkan pemeriksaan informasi.')

@php $authErrorTargets = ['email' => 'email', 'password' => 'password', 'remember' => 'remember_me']; @endphp

@section('content')
<div class="hl-auth-title">
    <h1>Masuk</h1>
    <p>Masuk ke akun Anda untuk melanjutkan pemeriksaan informasi.</p>
</div>

<form method="POST" action="{{ route('login.store') }}" id="login-form" class="hl-auth-form" data-submit-label="Memproses...">
    @csrf
    <x-ui.field id="email" label="Alamat email" :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="is_string(old('email')) ? old('email') : ''" required
            autocomplete="email" autocapitalize="none" spellcheck="false" :autofocus="! $errors->any()" />
    </x-ui.field>
    <x-ui.field id="password" label="Kata sandi" :error="$errors->first('password')">
        <x-forms.password name="password" required autocomplete="current-password" />
    </x-ui.field>
    <div class="hl-auth-options">
        <div class="hl-auth-choice">
            <input type="checkbox" id="remember_me" name="remember" value="1" @checked(in_array(old('remember'), [true, 1, '1'], true))
                aria-invalid="{{ $errors->has('remember') ? 'true' : 'false' }}" @if($errors->has('remember')) aria-describedby="remember-error" @endif>
            <label for="remember_me">Ingat saya</label>
        </div>
        <x-ui.link :href="route('password.request')">Lupa kata sandi?</x-ui.link>
    </div>
    @error('remember')
        <p id="remember-error" class="hl-field__error">{{ $message }}</p>
    @enderror
    <x-ui.button type="submit" id="login-btn">Masuk</x-ui.button>
</form>
<p class="hl-auth-switch">Belum punya akun? <x-ui.link :href="route('register')">Buat akun</x-ui.link></p>
<x-ui.link :href="route('home')" variant="tertiary" class="hl-auth-guest">Lanjutkan sebagai tamu</x-ui.link>
@endsection
