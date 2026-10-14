@extends('errors.layout')
@section('code', '503')
@section('title', 'Layanan belum tersedia')
@section('message', 'Layanan sementara belum dapat menerima permintaan ini.')
@section('recovery', 'Coba buka kembali halaman yang dituju beberapa saat lagi, atau kembali ke beranda.')
@section('details')
    @include('errors.retry-after')
@endsection
