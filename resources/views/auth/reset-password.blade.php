@extends('layouts.auth')

@section('title', 'Reset Kata Sandi — hoaxlin.id')

@section('content')
<div class="hl-auth-title">
    <h1>Buat Kata Sandi Baru</h1>
    <p style="color:var(--color-text-muted); font-size:0.9375rem;">Gunakan kata sandi baru untuk akun kamu.</p>
</div>

<form method="POST" action="{{ route('password.store') }}" data-submit-label="Menyimpan...">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <x-ui.field id="email" label="Alamat Email" :error="$errors->first('email')">
        <x-forms.input type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="email" />
    </x-ui.field>

    <x-ui.field id="password" label="Kata Sandi Baru" :error="$errors->first('password')">
        <x-forms.password name="password" required autocomplete="new-password" />
    </x-ui.field>

    <x-ui.field id="password_confirmation" label="Konfirmasi Kata Sandi" :error="$errors->first('password_confirmation')">
        <x-forms.password name="password_confirmation" required autocomplete="new-password" />
    </x-ui.field>

    <x-ui.button type="submit" class="w-full">Reset Kata Sandi</x-ui.button>
</form>
@endsection
