@props(['result'])
@php $state = \App\Enums\ExplanationStatus::tryFrom($result->explanation_status ?? ''); @endphp
<section class="hl-result__section" aria-labelledby="explanation-heading" data-explanation-state="{{ $state?->value ?? 'unknown' }}">
    <h2 id="explanation-heading">Penjelasan analisis</h2>
    <p class="hl-result__hint">Status tercatat: {{ $state?->label() ?? 'Belum tersedia' }}.</p>
    @if($state === \App\Enums\ExplanationStatus::Ready && filled($result->explanation))
        <p class="hl-result__text">{{ $result->explanation }}</p>
    @elseif($state === \App\Enums\ExplanationStatus::Pending)
        <p>Penjelasan AI belum tersedia untuk hasil ini.</p>
    @elseif($state === \App\Enums\ExplanationStatus::Unavailable)
        <p>Penjelasan AI tidak tersedia. Prediksi classifier tetap ditampilkan.</p>
    @else
        <p>Teks penjelasan tidak tersedia untuk hasil ini. Prediksi classifier tetap ditampilkan.</p>
    @endif
</section>
