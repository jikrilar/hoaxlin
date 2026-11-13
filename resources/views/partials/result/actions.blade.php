@php
    // Clipboard payload: label, plain-language sentence and date only.
    // Deliberately excludes the submission ID and any URL so a pasted summary
    // cannot be used as a sharing capability or fabricated citation.
    $summaryPrediction = $result ? \App\View\Presenters\DetectionPresentation::label($result->label) : null;
    $summaryText = $summaryPrediction === null ? null : implode("\n", [
        'Hoaxlin — ringkasan hasil',
        $summaryPrediction['text'].'. '.$summaryPrediction['description'],
        'Dikirim: '.($submission->created_at?->format('d M Y') ?? 'Tidak tersedia'),
        'Hasil merupakan prediksi model, bukan verifikasi kebenaran.',
    ]);
@endphp
<nav class="hl-result-actions" aria-label="Tindakan hasil">
    <a class="hl-button" data-variant="primary" href="{{ route('home') }}#cek-berita">Periksa informasi lain</a>
    @if($result)
        <a class="hl-button" data-variant="secondary" href="{{ route('hasil.pdf', $submission->id) }}">Unduh PDF</a>
        @if($summaryText !== null)
            <template data-summary-source>{{ $summaryText }}</template>
            <button type="button" class="hl-button" data-variant="secondary" data-copy-summary
                aria-describedby="copy-summary-status" hidden>Salin ringkasan</button>
            <p class="hl-visually-hidden" id="copy-summary-status" role="status" aria-live="polite" data-copy-summary-status></p>
        @endif
    @endif
    @if($verifiedViewer)
        <a class="hl-button" data-variant="secondary" href="{{ route('riwayat') }}">Kembali ke riwayat</a>
    @endif
</nav>
