@extends('errors.layout')
@section('code', '403')
@section('title', 'Akses tidak diizinkan')
@section('message', 'Kamu tidak memiliki izin untuk membuka halaman atau hasil yang dituju.')
@section('recovery', 'Gunakan akun yang memiliki akses, atau kembali ke beranda untuk memeriksa informasi lain.')
@section('actions')
    <x-ui.link :href="route('home', [], false)" variant="primary">Kembali ke beranda</x-ui.link>
    <x-ui.link :href="route('login', [], false)" variant="secondary">Masuk ke akun</x-ui.link>
@endsection
