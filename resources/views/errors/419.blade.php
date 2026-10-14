@extends('errors.layout')
@section('code', '419')
@section('title', 'Sesi atau form kedaluwarsa')
@section('message', 'Form yang dikirim tidak lagi memiliki sesi atau token keamanan yang valid.')
@section('recovery', 'Buka form baru sebelum mengirim ulang. File perlu dipilih ulang dan kata sandi perlu diisi kembali; halaman ini tidak memulihkan keduanya.')
@section('actions')
    <x-ui.link :href="route('home', [], false).'#cek-berita'" variant="primary">Buka form pemeriksaan baru</x-ui.link>
    <x-ui.link :href="route('login', [], false)" variant="secondary">Masuk ke akun</x-ui.link>
@endsection
