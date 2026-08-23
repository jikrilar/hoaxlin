<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>hoaxlin.id — Hasil Deteksi #{{ $submission->id }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1a1a2e; line-height: 1.6; }
  .header { text-align: center; border-bottom: 2px solid #6366f1; padding-bottom: 12px; margin-bottom: 16px; }
  .badge { display: inline-block; padding: 6px 14px; border-radius: 9999px; font-weight: 700; font-size: 11pt; }
  .badge.valid { background: #d1fae5; color: #065f46; }
  .badge.hoax { background: #fee2e2; color: #991b1b; }
  .badge.meragukan { background: #fef3c7; color: #92400e; }
  table { width: 100%; border-collapse: collapse; margin: 12px 0; }
  th, td { text-align: left; padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 9pt; }
  th { background: #f3f4f6; width: 28%; }
  .mono { font-family: monospace; font-size: 8pt; word-break: break-all; }
  .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 7pt; color: #6b7280; text-align: center; }
</style>
</head>
<body>
<div class="header">
  <h1 style="margin:0; font-size:16pt; color:#4338ca;">hoaxlin.id</h1>
  <p style="margin:0; font-size:9pt; color:#6b7280;">Indikasi probabilistik — bukan vonis hukum. Verifikasi ke Kominfo / Mafindo / CekFakta.</p>
</div>

<h2 style="font-size:12pt;">Hasil Deteksi #{{ $submission->id }}</h2>
@php $label = strtolower($result->label ?? 'meragukan'); @endphp
<p>
  <span class="badge {{ $label }}">{{ ucfirst($label) }}</span>
  <span style="margin-left:12px; font-size:11pt;"><strong>{{ round($result->confidence_score * 100) }}%</strong> keyakinan</span>
  <span style="margin-left:12px; font-size:8pt; color:#6b7280;">Model v{{ $result->model_version }}</span>
</p>

<table>
  <tr><th>ID</th><td>{{ $submission->id }}</td></tr>
  <tr><th>Tipe Input</th><td>{{ ucfirst($submission->input_type) }}</td></tr>
  <tr><th>Status</th><td>{{ ucfirst($submission->status) }} ({{ $submission->processing_stage }})</td></tr>
  <tr><th>Dibuat</th><td>{{ $submission->created_at?->format('d M Y H:i') }} WIB</td></tr>
  <tr><th>Sumber</th><td class="mono">{{ $submission->raw_input ?? $submission->source_url ?? $submission->media_path ?? '-' }}</td></tr>
  @if($submission->extracted_text && $submission->input_type !== 'text')
    <tr><th>Teks Terekstraksi</th><td>{{ mb_substr($submission->extracted_text, 0, 800) }}</td></tr>
  @endif
  <tr><th>Raw Scores</th><td class="mono">{{ json_encode($result->raw_scores, JSON_UNESCAPED_UNICODE) }}</td></tr>
  <tr><th>Inferensi</th><td>{{ $result->inference_ms }} ms {{ $result->classifier_cached ? '(cached)' : '' }}</td></tr>
</table>

<h3 style="font-size:10pt; margin-top:16px;">Penjelasan AI</h3>
<p>{{ $result->explanation ?? '—' }}</p>

<div class="footer">
  hoaxlin.id — {{ now()->format('d M Y H:i') }} WIB &nbsp;|&nbsp; Dokumen ini dihasilkan otomatis. &nbsp;|&nbsp; hoaxlin.id © {{ date('Y') }}
</div>
</body>
</html>
