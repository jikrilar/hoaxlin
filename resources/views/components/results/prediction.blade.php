@props(['label' => null, 'confidence' => null])
@php
    $prediction = \App\View\Presenters\DetectionPresentation::label($label);
    $score = \App\View\Presenters\DetectionPresentation::confidence($confidence);
    $badgeTone = match ($prediction['tone']) {
        'danger' => 'danger',
        'warning' => 'warning',
        default => 'neutral',
    };
@endphp

{{-- Summary (T2.7): badge + plain-language sentence + the model score. No severity bands. --}}
<div class="hl-result-prediction" data-result-label data-tone="{{ $prediction['tone'] }}">
    <p class="hl-result-prediction__label">
        <x-ui.badge :tone="$badgeTone" data-confidence-badge>{{ $prediction['text'] }}</x-ui.badge>
    </p>
    <p class="hl-result-prediction__description" data-result-description>{{ $prediction['description'] }}</p>
</div>

<dl class="hl-result__metadata hl-result-prediction__score">
    <div>
        <dt><x-ui.term key="confidence">Skor Keyakinan Model</x-ui.term></dt>
        <dd data-result-confidence>{{ $score ?? 'Tidak tersedia' }}</dd>
    </div>
</dl>

<x-ui.disclaimer class="hl-result__disclaimer">
    Skor ini menunjukkan tingkat keyakinan <x-ui.term key="classifier">model analisis</x-ui.term> pada prediksinya, bukan persentase kebenaran berita.
</x-ui.disclaimer>
