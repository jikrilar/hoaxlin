@extends('layouts.auth')

@section('title', 'Buat akun — hoaxlin.id')
@section('description', 'Buat akun hoaxlin.id untuk memeriksa tautan dan media. Verifikasi email untuk mengakses riwayat dan memberikan umpan balik.')

@php $authErrorTargets = ['name' => 'name', 'email' => 'email', 'password' => 'password', 'password_confirmation' => 'password_confirmation', 'terms' => 'terms']; @endphp

@section('content')
<div class="hl-auth-title">
    <h1>Buat akun</h1>
    <p>Gunakan akun untuk memeriksa tautan dan media. Setelah mendaftar, verifikasi email untuk mengakses riwayat dan memberikan umpan balik.</p>
</div>

<form method="POST" action="{{ route('register.store') }}" id="register-form" class="hl-auth-form" data-submit-label="Membuat akun...">
    @csrf
    <x-ui.field id="name" label="Nama lengkap" :error="$errors->first('name')">
        <x-forms.input name="name" :value="is_string(old('name')) ? old('name') : ''" required maxlength="255"
            autocomplete="name" :autofocus="! $errors->any()" />
    </x-ui.field>
    <x-ui.field id="email" label="Alamat email" hint="Gunakan alamat email dengan huruf kecil." :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="is_string(old('email')) ? old('email') : ''" required maxlength="255"
            autocomplete="email" autocapitalize="none" spellcheck="false" />
    </x-ui.field>
    <x-ui.field id="password" label="Kata sandi" hint="Gunakan kata sandi yang unik untuk akun ini." :error="$errors->first('password')">
        <x-forms.password name="password" required autocomplete="new-password" />
    </x-ui.field>
    <x-ui.field id="password_confirmation" label="Konfirmasi kata sandi" hint="Masukkan kembali kata sandi yang sama." :error="$errors->first('password_confirmation')">
        <x-forms.password name="password_confirmation" required autocomplete="new-password" />
    </x-ui.field>
    <div class="hl-auth-choice">
        <input type="checkbox" id="terms" name="terms" value="1" required @checked(in_array(old('terms'), [true, 1, '1', 'yes', 'on', 'true'], true))
            aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}" @if($errors->has('terms')) aria-describedby="terms-error" @endif>
        <label for="terms">Saya menyetujui <x-ui.link :href="route('kebijakan-privasi')">Kebijakan Privasi</x-ui.link> dan memahami bahwa hasil analisis merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</label>
    </div>
    @error('terms')
        <p id="terms-error" class="hl-field__error">{{ $message }}</p>
    @enderror
    <x-ui.button type="submit" id="register-btn">Buat akun</x-ui.button>
</form>
<p class="hl-auth-switch">Sudah punya akun? <x-ui.link :href="route('login')">Masuk</x-ui.link></p>
@endsection
