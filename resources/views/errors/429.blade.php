@extends('errors.layout')
@section('code', '429')
@section('title', 'Terlalu banyak permintaan')
@section('message', 'Batas permintaan layanan sedang tercapai.')
@section('recovery', 'Tunggu sebelum membuka kembali halaman yang dituju. Jangan mengirim form berulang kali.')
@section('details')
    @include('errors.retry-after')
@endsection
