@extends('layouts.app')

@section('title', 'Periksa informasi — hoaxlin.id')
@section('description', 'Periksa informasi dengan prediksi model dan rujukan terkait. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
<div class="hl-submission-page container-app" data-page="submission">
    <header class="hl-submission-intro">
        <h1 class="hl-heading-editorial">Periksa sebelum percaya.</h1>
        <p>Tempel informasi untuk mendapatkan prediksi model dan rujukan terkait.</p>
    </header>

    @php
        $previousInputType = old('input_type');
        $activeTab = auth()->check() ? match ($previousInputType) {
            'image' => 'gambar',
            'video', 'video_url' => 'video',
            'url' => 'url',
            default => 'teks',
        } : 'teks';
        $videoUrlActive = $activeTab === 'video' && $previousInputType === 'video_url';
        $previousRawInput = old('raw_input');
        $previousRawInput = is_string($previousRawInput) ? $previousRawInput : '';
        $previousSourceUrl = old('source_url');
        $previousSourceUrl = is_string($previousSourceUrl) ? $previousSourceUrl : '';
    @endphp

    <section id="cek-berita" class="hl-submission-surface" aria-labelledby="submission-title" data-submission-selector data-active-input="{{ $activeTab }}" data-active-video="{{ $videoUrlActive ? 'url' : 'upload' }}">
        <h2 id="submission-title">Pemeriksaan informasi</h2>
        @include('partials.submission.errors')
        @auth
            @include('partials.submission.input-types')
        @endauth
        @guest
            <p class="hl-submission-access">Kamu dapat memeriksa teks tanpa akun. <x-ui.link :href="route('login')">Masuk</x-ui.link> untuk menggunakan URL, gambar, atau video.</p>
        @endguest
        <div id="panel-teks" class="hl-input-panel" data-input-panel="teks">
            @auth <h3>Teks</h3> @endauth
            @include('partials.submission.text')
        </div>
        @include('partials.submission.media')
        <p class="hl-submission-note">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif. Periksa kembali informasi melalui sumber terpercaya.</p>
    </section>

    <section id="cara-kerja" class="hl-submission-help" aria-labelledby="submission-help-title">
        <h2 id="submission-help-title">Cara memeriksa informasi</h2>
        <ol>
            <li>Masukkan informasi dan jawab pertanyaan keamanan.</li>
            <li>Baca prediksi model, penjelasan, dan rujukan yang tersedia.</li>
            <li>Bandingkan informasi dengan sumber terpercaya sebelum membagikannya.</li>
        </ol>
        <x-ui.link :href="route('cara-kerja')">Pelajari cara kerja</x-ui.link>
    </section>

    <x-ui.disclosure title="Informasi model dan evaluasi" class="hl-submission-model" id="submission-model">
        <p>Data berikut menjelaskan model, evaluasi, dan telemetry yang tersedia. Data ini bukan jaminan kebenaran setiap pemeriksaan.</p>
        <dl class="hl-submission-statistics">
            @foreach($modelStatistics as $stat)
                <div>
                    <dt>{{ $stat['label'] }}</dt>
                    <dd>{{ $stat['value'] }} <span>{{ $stat['description'] }}</span></dd>
                </div>
            @endforeach
        </dl>
        <x-ui.link :href="route('statistik')">Lihat statistik dan konteks evaluasi</x-ui.link>
    </x-ui.disclosure>
</div>
@endsection
