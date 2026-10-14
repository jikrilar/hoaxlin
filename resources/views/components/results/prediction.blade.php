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
<p class="hl-result__disclaimer">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
<dl class="hl-result__metadata">
    <div><dt>Skor Keyakinan Model</dt><dd data-result-confidence>{{ $score ?? 'Tidak tersedia' }}</dd></div>
</dl>
<p class="hl-result__hint">Skor ini menunjukkan keyakinan classifier pada prediksinya, bukan persentase kebenaran berita.</p>
