@php
    use App\Enums\ExplanationStatus;
    use App\View\Presenters\DetectionPresentation;

    $prediction = DetectionPresentation::label($result->label);
    $score = DetectionPresentation::confidence($result->confidence_score);
    $explanationState = ExplanationStatus::tryFrom($result->explanation_status ?? '');
    $timezone = config('app.timezone');
    $sourceUrl = DetectionPresentation::sourceUrl($submission->source_url);
    $excerptLimit = 1600;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Hoaxlin — Hasil pemeriksaan #{{ $submission->id }}</title>
    {{-- Print styles are independent of the web theme. DejaVu fonts ship with DomPDF. --}}
    <style>
        @page { size: A4 portrait; margin: 18mm; }
        body { margin: 0; background: white; color: #252A27; font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.4; word-wrap: break-word; }
        h1, h2, h3 { page-break-after: avoid; }
        h1, h2 { font-family: DejaVu Serif, serif; font-weight: normal; }
        h1 { margin: 4pt 0 6pt; font-size: 19pt; line-height: 1.25; }
        h2 { margin: 14pt 0 6pt; font-size: 14pt; }
        h3 { margin: 8pt 0 4pt; font-size: 10pt; }
        p { margin: 0 0 5pt; orphans: 3; widows: 3; }
        .brand { color: #235C50; font-size: 12pt; font-weight: bold; }
        .header { border-bottom: 1pt solid #235C50; padding-bottom: 9pt; margin-bottom: 12pt; page-break-inside: avoid; }
        .muted, .hint, .footer { color: #596159; font-size: 9pt; }
        .prediction { padding: 10pt 12pt; border-left: 3pt solid; margin-bottom: 9pt; page-break-inside: avoid; }
        .prediction p:last-child { margin-bottom: 0; }
        .prediction-label { font-weight: bold; font-size: 11pt; }
        .neutral { color: #252A27; background: #F7F6F2; border-color: #596159; }
        .danger { color: #8B2D2D; background: #FBEEEB; border-color: #8B2D2D; }
        .warning { color: #765719; background: #FBF3DF; border-color: #765719; }
        .metadata { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9pt; margin: 6pt 0; }
        .metadata th, .metadata td { padding: 3pt 0; vertical-align: top; border-bottom: 0.5pt solid #DADDD7; text-align: left; }
        .metadata th { width: 35%; font-weight: normal; color: #596159; padding-right: 10pt; }
        .text { white-space: pre-wrap; }
        .footer { margin-top: 12pt; padding-top: 8pt; border-top: 0.5pt solid #DADDD7; page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="header">
        <p class="brand">Hoaxlin</p>
        <h1>Hasil pemeriksaan informasi</h1>
        <p class="muted">ID {{ $submission->id }} · Status: Selesai</p>
        <p class="muted">Dikirim: {{ $submission->created_at?->copy()->timezone($timezone)->format('d M Y, H:i T') ?? 'Tidak tersedia' }}</p>
    </div>

    <div class="prediction {{ $prediction['tone'] }}">
        <p class="prediction-label">{{ $prediction['text'] }}</p>
        @if($prediction['abstained'])
            <p>Ini bukan bukti bahwa informasi benar atau palsu.</p>
        @endif
    </div>
    <p>Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
    <table class="metadata">
        <tr><th>Skor Keyakinan Model</th><td>{{ $score ?? 'Tidak tersedia' }}</td></tr>
        <tr><th>Versi classifier</th><td>{{ filled($result->model_version) ? $result->model_version : 'Tidak tersedia' }}</td></tr>
    </table>
    <p class="hint">Skor ini menunjukkan keyakinan classifier pada prediksinya, bukan persentase kebenaran berita.</p>

    <h2>Input dan provenance</h2>
    <p>Jenis input: {{ DetectionPresentation::inputType($submission->input_type) }}</p>
    @if(filled($submission->raw_input))
        <h3>Input asli</h3>
        @if(mb_strlen($submission->raw_input) > $excerptLimit)
            <p class="hint">Cuplikan {{ $excerptLimit }} karakter pertama; input asli tidak ditampilkan seluruhnya.</p>
        @endif
        <p class="text">{{ mb_substr($submission->raw_input, 0, $excerptLimit) }}</p>
    @endif
    @if(filled($submission->source_url))
        <h3>Tautan sumber input</h3>
        {{-- Plain text avoids turning untrusted schemes into PDF link annotations. --}}
        <p class="text">{{ $sourceUrl ?? 'Tautan sumber tidak dapat ditampilkan karena formatnya tidak aman.' }}</p>
    @endif
    @if(in_array($submission->input_type, ['image', 'video'], true))
        <p>Media dikirim secara privat. Berkas media tidak disertakan dalam PDF ini.</p>
    @elseif(! filled($submission->raw_input) && ! filled($submission->source_url))
        <p>Input asli tidak tersedia.</p>
    @endif
    @foreach(['extracted_text' => 'Teks hasil ekstraksi', 'translated_text' => 'Terjemahan bahasa Indonesia untuk analisis'] as $field => $heading)
        @if(filled($submission->{$field}))
            <h3>{{ $heading }}</h3>
            @if($field === 'translated_text')
                <p class="hint">Terjemahan digunakan untuk analisis dan berbeda dari input asli.</p>
            @endif
            @if(mb_strlen($submission->{$field}) > $excerptLimit)
                <p class="hint">Cuplikan {{ $excerptLimit }} karakter pertama; teks tidak ditampilkan seluruhnya.</p>
            @endif
            <p class="text">{{ mb_substr($submission->{$field}, 0, $excerptLimit) }}</p>
        @endif
    @endforeach
    <table class="metadata">
        <tr><th>Bahasa Sumber</th><td>{{ filled($submission->source_language) ? $submission->source_language : 'Tidak tersedia' }}</td></tr>
        <tr><th>Provider Terjemahan</th><td>{{ filled($submission->translation_provider) ? $submission->translation_provider : 'Tidak tersedia' }}</td></tr>
        <tr><th>Model Terjemahan</th><td>{{ filled($submission->translation_model) ? $submission->translation_model : 'Tidak tersedia' }}</td></tr>
        <tr><th>Versi pipeline</th><td>{{ filled($submission->pipeline_version) ? $submission->pipeline_version : 'Tidak tersedia' }}</td></tr>
        @if($submission->processing_completed_at)
            <tr><th>Pemrosesan selesai</th><td>{{ $submission->processing_completed_at->copy()->timezone($timezone)->format('d M Y, H:i T') }}</td></tr>
        @endif
    </table>

    <h2>Penjelasan analisis</h2>
    <p class="hint">Status tercatat: {{ $explanationState?->label() ?? 'Belum tersedia' }}.</p>
    @if($explanationState === ExplanationStatus::Ready && filled($result->explanation))
        <p class="text">{{ $result->explanation }}</p>
    @elseif($explanationState === ExplanationStatus::Pending)
        <p>Penjelasan AI belum tersedia untuk hasil ini.</p>
    @elseif($explanationState === ExplanationStatus::Unavailable)
        <p>Penjelasan AI tidak tersedia. Prediksi classifier tetap ditampilkan.</p>
    @else
        <p>Teks penjelasan tidak tersedia untuk hasil ini. Prediksi classifier tetap ditampilkan.</p>
    @endif
    <p class="hint">Model penjelasan: {{ filled($result->explanation_model) ? $result->explanation_model : 'Tidak tersedia' }}.</p>

    <div class="footer">
        <p>Hoaxlin · Dokumen dihasilkan pada {{ now()->timezone($timezone)->format('d M Y, H:i T') }}.</p>
        <p>PDF ini merupakan salinan hasil saat diunduh. Periksa informasi bersama sumber tepercaya.</p>
    </div>
</body>
</html>
