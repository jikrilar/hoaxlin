@props(['label' => null, 'confidence' => null])
@php
    $prediction = \App\View\Presenters\DetectionPresentation::label($label);
    $score = \App\View\Presenters\DetectionPresentation::confidence($confidence);
@endphp
<div class="hl-result-prediction" data-result-label data-tone="{{ $prediction['tone'] }}">
    <p class="hl-result-prediction__label">{{ $prediction['text'] }}</p>
    @if($prediction['abstained'])
        <p>Ini bukan bukti bahwa informasi benar atau palsu.</p>
    @endif
</div>
<x-ui.disclaimer class="hl-result__disclaimer">
    Skor ini menunjukkan tingkat keyakinan <x-ui.term key="classifier">model analisis</x-ui.term> pada prediksinya, bukan persentase kebenaran berita.
</x-ui.disclaimer>
<dl class="hl-result__metadata">
    <div><dt><x-ui.term key="confidence">Skor Keyakinan Model</x-ui.term></dt><dd data-result-confidence>{{ $score ?? 'Tidak tersedia' }}</dd></div>
</dl>
