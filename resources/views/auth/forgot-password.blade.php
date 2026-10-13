@extends('layouts.auth')

@section('title', 'Lupa kata sandi — hoaxlin.id')
@section('description', 'Minta tautan reset kata sandi akun hoaxlin.id melalui alamat email Anda.')

@php $authErrorTargets = ['email' => 'email']; @endphp

@section('content')
<div class="hl-auth-title">
    <h1>Lupa kata sandi?</h1>
    <p>Masukkan alamat email akun Anda untuk meminta tautan reset kata sandi.</p>
</div>

<form method="POST" action="{{ route('password.email') }}" id="forgot-form" class="hl-auth-form" data-submit-label="Mengirim...">
    @csrf
    <x-ui.field id="email" label="Alamat email" hint="Gunakan alamat email yang Anda gunakan saat mendaftar." :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="is_string(old('email')) ? old('email') : ''" required
            autocomplete="email" autocapitalize="none" spellcheck="false" :autofocus="! $errors->any()" />
    </x-ui.field>
    <x-ui.button type="submit" id="reset-btn">Kirim tautan reset</x-ui.button>
</form>
<p class="hl-field__hint">Periksa kotak masuk dan folder spam setelah meminta tautan reset.</p>
<p class="hl-auth-switch"><x-ui.link :href="route('login')">Kembali ke halaman masuk</x-ui.link></p>
@endsection
