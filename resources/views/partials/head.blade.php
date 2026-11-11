@php
    // Sections remain the page contract. Metadata never derives from submissions.
    // Inline Blade sections arrive escaped; normalize once and escape below.
    $pageTitle = html_entity_decode($__env->yieldContent('title', 'hoaxlin.id — Periksa informasi'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $pageDescription = html_entity_decode($__env->yieldContent('description', 'Periksa informasi dengan bantuan analisis AI. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<meta name="keywords" content="cek hoax, deteksi hoax, berita hoax, BERT, AI, fact check, verifikasi berita">
<meta name="author" content="Muhamad Jikril Aryanda">{{-- TODO(E-1): owner decision pending; align with footer credit. --}}

<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:type" content="website">

<link rel="icon" type="image/svg+xml" href="/favicon.svg">

{{-- Licensed Inter/Newsreader fonts are self-hosted through the Vite stylesheet. --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
@stack('styles')
