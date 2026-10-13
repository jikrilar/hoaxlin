@extends('layouts.auth')

@section('title', 'Reset kata sandi — hoaxlin.id')
@section('description', 'Tetapkan kata sandi baru melalui tautan reset akun hoaxlin.id.')

@php
    $authErrorTargets = ['email' => 'email', 'password' => 'password', 'password_confirmation' => 'password_confirmation'];
    $resetEmail = old('email', $request->input('email'));
@endphp

@section('content')
<div class="hl-auth-title">
    <h1>Buat kata sandi baru</h1>
    <p>Masukkan email akun dan kata sandi baru. Tautan reset diperiksa saat perubahan dikirim.</p>
</div>

<form method="POST" action="{{ route('password.store') }}" id="password-reset-form" class="hl-auth-form" data-submit-label="Menyimpan...">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <x-ui.field id="email" label="Alamat email" :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="is_string($resetEmail) ? $resetEmail : ''" required
            autocomplete="email" autocapitalize="none" spellcheck="false" :autofocus="! $errors->any()" />
    </x-ui.field>
    <x-ui.field id="password" label="Kata sandi baru" hint="Gunakan kata sandi yang unik untuk akun ini." :error="$errors->first('password')">
        <x-forms.password name="password" required autocomplete="new-password" />
    </x-ui.field>
    <x-ui.field id="password_confirmation" label="Konfirmasi kata sandi" hint="Masukkan kembali kata sandi yang sama." :error="$errors->first('password_confirmation')">
        <x-forms.password name="password_confirmation" required autocomplete="new-password" />
    </x-ui.field>
    <x-ui.button type="submit" id="password-reset-submit">Simpan kata sandi baru</x-ui.button>
</form>
<p class="hl-auth-switch"><x-ui.link :href="route('password.request')">Minta tautan reset baru</x-ui.link></p>
<p class="hl-auth-switch"><x-ui.link :href="route('login')">Kembali ke halaman masuk</x-ui.link></p>
@endsection
