@extends('layouts.app')

@section('title', 'Hasil analisis — hoaxlin.id')
@section('description', 'Baca prediksi model dan referensi terkait. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
@php
    $result = \App\View\Presenters\DetectionPresentation::finalResult($submission, $result);
    $resultViewer = auth()->user();
    $verifiedViewer = $resultViewer?->hasVerifiedEmail() ?? false;
    $ownsResult = $resultViewer !== null && $submission->user_id !== null
        && $resultViewer->getKey() === $submission->user_id;
@endphp
<div data-page="result" class="hl-result-page">
    <div class="container-app hl-result-page__inner">
        <a href="{{ request()->routeIs('riwayat.show') ? route('riwayat') : url('/') }}" class="hl-button hl-result-page__back" data-variant="tertiary">
            {{ request()->routeIs('riwayat.show') ? 'Kembali ke Riwayat' : 'Kembali ke Beranda' }}
        </a>
        <div class="hl-result-page__content">
            @if(! $submission->isCompleted())
                <div id="submission-progress-wrapper" data-progress-root data-submission-id="{{ $submission->id }}"
                    data-initial-status="{{ $submission->status }}" data-status-url="{{ route('hasil.status', $submission->id) }}"
                    data-progress-stages="{{ json_encode(\App\Enums\ProcessingStage::values()) }}">
                    <livewire:submission-progress :submission="$submission" />
                    <p class="hl-submission-progress__hint" data-progress-transport role="status" aria-live="polite" aria-atomic="true" hidden></p>
                    <noscript><p class="hl-submission-progress__hint">JavaScript dinonaktifkan. Gunakan “Muat ulang status” untuk melihat pembaruan.</p></noscript>
                </div>
                @if($submission->isFailed() && $failureReason)
                    <x-ui.alert tone="danger">{{ $failureReason }}</x-ui.alert>
                @endif
                @include('partials.result.timestamps')
            @elseif(! $result)
                <section aria-labelledby="result-heading">
                    <h1 id="result-heading">Hasil final tidak tersedia</h1>
                    <p>Pemrosesan tercatat selesai, tetapi hasil analisis yang sesuai belum tersedia. Anda dapat mengirim pemeriksaan baru.</p>
                    @include('partials.result.timestamps')
                </section>
            @else
                @include('partials.result.final')
            @endif
            @include('partials.result.provenance')
        </div>
        @include('partials.result.feedback')
        @include('partials.result.actions')
    </div>
</div>
@endsection
