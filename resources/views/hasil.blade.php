@extends('layouts.app')

@section('title', 'Hasil analisis — hoaxlin.id')
@section('description', 'Baca prediksi model dan referensi terkait. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.')

@section('content')
@php $result = \App\View\Presenters\DetectionPresentation::finalResult($submission, $result); @endphp
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
        @include('partials.result.actions-legacy')
    </div>
</div>
@endsection

@push('scripts')
<script>
// Feedback selection
function selectFeedback(isCorrect) {
    document.getElementById('feedback-is-correct').value = isCorrect ? '1' : '0';
    const btnCorrect = document.getElementById('fb-correct');
    const btnWrong = document.getElementById('fb-wrong');
    const commentArea = document.getElementById('feedback-comment-area');

    if (isCorrect) {
        btnCorrect.style.background = 'var(--ds-success-bg)';
        btnCorrect.style.borderColor = 'var(--ds-success-fg)';
        btnCorrect.style.color = 'var(--ds-success-fg)';
        btnCorrect.setAttribute('aria-pressed', 'true');
        btnWrong.style.background = 'transparent';
        btnWrong.style.borderColor = 'var(--ds-border-control)';
        btnWrong.style.color = 'var(--color-primary-light)';
        btnWrong.setAttribute('aria-pressed', 'false');
        commentArea.style.display = 'block';
    } else {
        btnWrong.style.background = 'var(--ds-danger-bg)';
        btnWrong.style.borderColor = 'var(--ds-danger-fg)';
        btnWrong.style.color = 'var(--ds-danger-fg)';
        btnWrong.setAttribute('aria-pressed', 'true');
        btnCorrect.style.background = 'transparent';
        btnCorrect.style.borderColor = 'var(--ds-border-control)';
        btnCorrect.style.color = 'var(--color-primary-light)';
        btnCorrect.setAttribute('aria-pressed', 'false');
        commentArea.style.display = 'block';
        commentArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
@endpush
