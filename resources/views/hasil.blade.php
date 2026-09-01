@extends('layouts.app')

@section('title', 'Hasil Deteksi — hoaxlin.id')
@section('description', 'Lihat hasil analisis deteksi hoax dari sistem AI BERT.')

@section('content')
<div style="padding:9rem 0 5rem; min-height:100vh;">
    <div class="container-app" style="max-width:800px;">

        <!-- Back Button -->
        <a href="{{ request()->routeIs('riwayat.show') ? route('riwayat') : url('/') }}" class="btn-ghost" style="display:inline-flex; margin-bottom:2rem;" aria-label="Kembali">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            {{ request()->routeIs('riwayat.show') ? 'Kembali ke Riwayat' : 'Kembali ke Beranda' }}
        </a>

        <!-- Result Header -->
        <div class="glass-card animate-fade-in-up" style="padding:2.5rem; margin-bottom:1.5rem;" role="main" aria-label="Hasil deteksi berita">
            @if(! $result)
                {{-- Livewire polling progress bar (wire:poll.2s.visible) with JS fallback via /hasil/{id}/status --}}
                <div id="submission-progress-wrapper" data-submission-id="{{ $submission->id }}">
                    <livewire:submission-progress :submission="$submission" />
                    {{-- Fallback for no-JS / Livewire failure: static bar + meta refresh --}}
                    <noscript>
                        <div style="text-align:center; padding:1rem; color:var(--color-text-muted); font-size:0.875rem;">
                            JavaScript dinonaktifkan — <a href="{{ route('hasil', $submission->id) }}" style="color:var(--color-primary-light);">muat ulang</a> untuk melihat progress terbaru.
                        </div>
                        <meta http-equiv="refresh" content="5;url={{ route('hasil', $submission->id) }}">
                    </noscript>
                </div>
                {{-- Vanilla JS polling fallback (if Livewire not loaded, polls /hasil/{id}/status) --}}
                <script>
                    (() => {
                        const wrapper = document.getElementById('submission-progress-wrapper');
                        // Correctly detect Livewire: check for wire:id (Livewire 3/4) or Livewire global
                        const hasLivewire = wrapper && (wrapper.querySelector('[wire\\:id]') || wrapper.querySelector('[wire\\:poll], [wire\\:poll\\.2s], [wire\\:poll\\.2s\\.visible]') || typeof window.Livewire !== 'undefined');
                        if (! wrapper || hasLivewire) return;
                        const url = `{{ route('hasil.status', $submission->id) }}`;
                        let interval = setInterval(async () => {
                            try {
                                const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                                if (! res.ok) return;
                                const data = await res.json();
                                const bar = wrapper.querySelector('[role="progressbar"]');
                                const pctEl = wrapper.querySelector('[aria-live="polite"]');
                                const stageEl = wrapper.querySelector('[data-stage-label]');
                                if (bar && typeof data.progress === 'number') {
                                    bar.setAttribute('aria-valuenow', data.progress);
                                    const fill = bar.firstElementChild;
                                    if (fill) {
                                        fill.style.width = data.progress + '%';
                                        fill.style.willChange = 'width';
                                    }
                                }
                                if (pctEl && typeof data.progress === 'number') pctEl.textContent = data.progress + '%';
                                if (stageEl && data.stage_label) stageEl.textContent = data.stage_label;
                                if (data.is_completed || data.is_failed || data.has_result) {
                                    clearInterval(interval);
                                    setTimeout(() => window.location.reload(), 800);
                                }
                            } catch (_) {}
                        }, 2000);
                        document.addEventListener('visibilitychange', () => {
                            if (document.hidden) clearInterval(interval);
                        });
                    })();
                </script>
                <div class="divider" style="margin:1.5rem 0;"></div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem;">
                    <div>
                        <p style="color:var(--color-text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:0.375rem;">Jenis Input</p>
                        <p style="color:var(--color-text-secondary);">{{ ucfirst($submission->input_type) }}</p>
                    </div>
                    <div>
                        <p style="color:var(--color-text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:0.375rem;">Dikirim</p>
                        <p style="color:var(--color-text-secondary);">{{ $submission->created_at->format('d M Y, H:i') }}</p>
                    </div>
                </div>
                @if($submission->raw_input)
                    <div style="padding:1.25rem; background:rgba(8,11,20,0.6); border:1px solid var(--color-border-light); border-radius:0.875rem;">
                        <p style="color:var(--color-text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:0.625rem;">Teks Dikirim</p>
                        <p style="color:var(--color-text-secondary); line-height:1.7; white-space:pre-wrap;">{{ $submission->raw_input }}</p>
                    </div>
                @elseif($submission->source_url)
                    <div style="padding:1.25rem; background:rgba(8,11,20,0.6); border:1px solid var(--color-border-light); border-radius:0.875rem; word-break:break-all;">
                        <p style="color:var(--color-text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:0.625rem;">URL Dikirim</p>
                        <a href="{{ $submission->source_url }}" target="_blank" rel="noopener noreferrer" style="color:var(--color-primary-light);">{{ $submission->source_url }}</a>
                    </div>
                @elseif($submission->media_path)
                    <div style="padding:1.25rem; background:rgba(8,11,20,0.6); border:1px solid var(--color-border-light); border-radius:0.875rem;">
                        <p style="color:var(--color-text-muted); font-size:0.75rem; text-transform:uppercase; margin-bottom:0.625rem;">Media Tersimpan</p>
                        <p style="color:var(--color-text-secondary); margin-bottom:0.75rem;">File {{ $submission->input_type }} berhasil disimpan secara privat.</p>
                        @if($submission->input_type === 'image')
                            <img src="{{ route('hasil.media', $submission->id) }}" alt="Preview media" style="max-width:100%; max-height:320px; border-radius:0.5rem; border:1px solid var(--color-border-light);">
                        @endif
                        <div style="margin-top:0.75rem;">
                            <a href="{{ route('hasil.media', $submission->id) }}" target="_blank" style="color:var(--color-primary-light); font-size:0.8125rem; text-decoration:underline;">Lihat / Unduh Media (tautan privat 5 menit)</a>
                        </div>
                    </div>
                @endif
                @if($submission->failure_reason)
                    <div class="warning-box" style="margin-top:1rem;">{{ $submission->failure_reason }}</div>
                @endif
            @else

            <!-- Label Badge & Score -->
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:1.5rem; margin-bottom:2rem;">
                <div>
                    <p style="color:var(--color-text-muted); font-size:0.875rem; margin-bottom:0.75rem; text-transform:uppercase; letter-spacing:0.06em;">Hasil Deteksi</p>
                    @php
                        $label = $result->label ?? 'meragukan';
                        $labelLower = strtolower($label);
                        $labelMap = ['valid' => 'Valid', 'hoax' => 'Hoax', 'meragukan' => 'Meragukan'];
                        $labelDisplay = $labelMap[$labelLower] ?? $label;
                        $icons = ['valid'=>'✅', 'hoax'=>'🚨', 'meragukan'=>'⚠️'];
                    @endphp
                    <div class="result-badge {{ $labelLower }}" role="status" aria-label="Label: {{ $labelDisplay }}">
                        <span aria-hidden="true">{{ $icons[$labelLower] ?? '❓' }}</span>
                        {{ $labelDisplay }}
                    </div>
                </div>

                <!-- Confidence Score -->
                <div style="text-align:right;">
                    <p style="color:var(--color-text-muted); font-size:0.875rem; margin-bottom:0.375rem;">Tingkat Keyakinan Model</p>
                    @php $confidence = (float) ($result->confidence_score ?? 0); @endphp
                    <p style="font-family:var(--font-display); font-size:2.5rem; font-weight:800; line-height:1;" aria-label="{{ round($confidence * 100) }} persen">
                        <span class="gradient-text">{{ round($confidence * 100) }}%</span>
                    </p>
                </div>
            </div>

            <!-- Confidence Bar -->
            <div style="margin-bottom:2rem;">
                <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                    <span style="color:var(--color-text-muted); font-size:0.8125rem;">Confidence Score</span>
                    <span style="color:var(--color-text-secondary); font-size:0.8125rem;">{{ round($confidence * 100) }} / 100</span>
                </div>
                <div class="confidence-bar-track" role="progressbar" aria-valuenow="{{ round($confidence * 100) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Confidence score {{ round($confidence * 100) }} persen">
                    <div class="confidence-bar-fill {{ $labelLower ?? 'meragukan' }}" id="confidence-fill" style="width:0%;"></div>
                </div>
            </div>

            <!-- Divider -->
            <div class="divider" style="margin-bottom:2rem;"></div>

            <!-- Input Type Info -->
            <div style="display:flex; gap:0.625rem; align-items:center; margin-bottom:1.5rem;">
                @php
                $inputTypes = [
                    'text' => ['icon'=>'🔤', 'label'=>'Input Teks'],
                    'image' => ['icon'=>'🖼️', 'label'=>'Input Gambar (OCR)'],
                    'video' => ['icon'=>'🎬', 'label'=>'Input Video (Transkripsi)'],
                    'url' => ['icon'=>'🔗', 'label'=>'Input Tautan URL'],
                ];
                $inputType = $submission->input_type ?? 'text';
                $inputInfo = $inputTypes[$inputType] ?? $inputTypes['text'];
                @endphp
                <span style="padding:0.25rem 0.75rem; background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.2); border-radius:2rem; color:var(--color-primary-light); font-size:0.8125rem;" role="status">
                    {{ $inputInfo['icon'] }} {{ $inputInfo['label'] }}
                </span>
                @if(isset($result->model_version))
                <span style="padding:0.25rem 0.75rem; background:rgba(6,182,212,0.08); border:1px solid rgba(6,182,212,0.2); border-radius:2rem; color:#22d3ee; font-size:0.8125rem;" title="Model version">
                    Model v{{ $result->model_version }}
                </span>
                @endif
                @if($submission->source_language === 'en' && filled($submission->translated_text))
                <span style="padding:0.25rem 0.75rem; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:2rem; color:#34d399; font-size:0.8125rem;" title="Teks Inggris diterjemahkan sebelum klasifikasi">
                    EN → ID · {{ $submission->translation_model }}
                </span>
                @endif
                <span style="padding:0.25rem 0.75rem; background:rgba(168,85,247,0.08); border:1px solid rgba(168,85,247,0.2); border-radius:2rem; color:#c4b5fd; font-size:0.8125rem;" title="Prompt version">
                    Prompt v{{ config('services.openai.prompt_version', '1.0') }}
                </span>
                <span style="color:var(--color-text-muted); font-size:0.8125rem; margin-left:auto;">
                    {{ now()->format('d M Y, H:i') }} WIB
                </span>
            </div>

            <!-- AI Explanation -->
            <div style="padding:1.5rem; background:rgba(13,17,23,0.8); border:1px solid var(--color-border); border-radius:1rem; margin-bottom:1.5rem;" role="region" aria-label="Penjelasan AI">
                <div style="display:flex; gap:0.75rem; align-items:flex-start; margin-bottom:1rem;">
                    <div style="width:2rem; height:2rem; background:rgba(99,102,241,0.12); border-radius:0.5rem; display:flex; align-items:center; justify-content:center; flex-shrink:0;" aria-hidden="true">🤖</div>
                    <div>
                        <p style="color:var(--color-text-primary); font-size:0.875rem; font-weight:600;">Penjelasan AI</p>
                        <p style="color:var(--color-text-muted); font-size:0.75rem;">Disusun berdasarkan hasil klasifikasi BERT</p>
                    </div>
                </div>
                <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.75;">
                    {{ $result->explanation ?? 'Berdasarkan analisis mendalam menggunakan model BERT yang telah dilatih pada korpus berita Indonesia, teks yang dimasukkan menunjukkan karakteristik yang konsisten dengan kategori yang terdeteksi. Indikator linguistik seperti gaya penulisan, pilihan kata, dan struktur kalimat telah dipertimbangkan dalam proses klasifikasi ini.' }}
                </p>
            </div>

            <!-- Extracted Text (if applicable) -->
            @if(isset($submission->extracted_text) && $inputType !== 'text')
            <div style="margin-bottom:1.5rem;">
                <button id="toggle-extracted" onclick="toggleExtracted()" style="display:flex; align-items:center; gap:0.5rem; background:none; border:none; color:var(--color-text-secondary); font-size:0.875rem; font-weight:500; cursor:pointer; padding:0;" aria-expanded="false" aria-controls="extracted-text-panel">
                    <svg id="toggle-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition:transform 0.2s;" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                    Lihat Teks yang Diekstraksi
                </button>
                <div id="extracted-text-panel" style="display:none; margin-top:1rem; padding:1.25rem; background:rgba(8,11,20,0.6); border:1px solid var(--color-border-light); border-radius:0.875rem; max-height:200px; overflow-y:auto;">
                    <p style="color:var(--color-text-secondary); font-size:0.875rem; line-height:1.7; white-space:pre-wrap;">{{ $submission->extracted_text }}</p>
                </div>
            </div>
            @endif

            @if(filled($submission->translated_text))
            <div style="margin-bottom:1.5rem; padding:1.25rem; background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.2); border-radius:0.875rem;">
                <div style="display:flex; justify-content:space-between; gap:1rem; margin-bottom:0.75rem;">
                    <p style="color:#34d399; font-size:0.875rem; font-weight:600;">Terjemahan Bahasa Indonesia untuk IndoBERT</p>
                    <span style="color:var(--color-text-muted); font-size:0.75rem;">{{ $submission->translation_model }}{{ $submission->translation_cached ? ' · cache' : '' }}</span>
                </div>
                <p style="color:var(--color-text-secondary); font-size:0.875rem; line-height:1.7; white-space:pre-wrap; max-height:240px; overflow-y:auto;">{{ $submission->translated_text }}</p>
            </div>
            @endif

            @endif

        </div>

        <!-- Feedback Section -->
        @if($result)
        <div class="glass-card animate-fade-in-up delay-200" style="padding:2rem; margin-bottom:1.5rem;" role="region" aria-label="Umpan balik">
            <h2 style="font-size:1.0625rem; font-weight:700; margin-bottom:0.5rem;">Apakah hasil ini akurat?</h2>
            <p style="color:var(--color-text-muted); font-size:0.875rem; margin-bottom:1.25rem;">
                Umpan balikmu membantu kami meningkatkan akurasi model BERT.
            </p>

            @auth
            @if($feedback ?? null)
                <div style="padding:1.25rem; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); border-radius:0.875rem;">
                    <p style="color:#34d399; font-weight:600; margin-bottom:0.375rem;">
                        {{ $feedback->is_correct ? '✅ Prediksi ditandai benar' : '❌ Prediksi ditandai tidak benar' }}
                    </p>
                    @if($feedback->comment)
                        <p style="color:var(--color-text-secondary); font-size:0.875rem; line-height:1.6; margin-bottom:0.5rem;">{{ $feedback->comment }}</p>
                    @endif
                    <p style="color:var(--color-text-muted); font-size:0.75rem;">Dikirim {{ $feedback->created_at->format('d M Y, H:i') }}</p>
                </div>
            @elseif(auth()->id() === $submission->user_id)
            <form action="{{ route('feedback') }}" method="POST" id="feedback-form">
                @csrf
                <input type="hidden" name="submission_id" value="{{ $submission->id ?? '' }}">

                <div style="display:flex; gap:0.75rem; margin-bottom:1.25rem;" role="group" aria-label="Pilih umpan balik">
                    <button type="button" id="fb-correct" onclick="selectFeedback(true)"
                        class="btn-secondary" style="flex:1; justify-content:center;"
                        aria-pressed="false">
                        ✅ Ya, Sudah Benar
                    </button>
                    <button type="button" id="fb-wrong" onclick="selectFeedback(false)"
                        class="btn-secondary" style="flex:1; justify-content:center;"
                        aria-pressed="false">
                        ❌ Tidak Akurat
                    </button>
                </div>

                <input type="hidden" name="is_correct" id="feedback-is-correct" value="">

                @error('is_correct')
                    <p class="auth-error" role="alert" style="margin-bottom:1rem;">{{ $message }}</p>
                @enderror

                <div id="feedback-comment-area" style="display:none;">
                    <label for="feedback-comment" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; margin-bottom:0.5rem;">Komentar (opsional)</label>
                    <textarea id="feedback-comment" name="comment" class="form-textarea" style="height:100px;" maxlength="1000" placeholder="Tambahkan komentar tentang hasil prediksi...">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn-primary" style="margin-top:1rem;">
                        <span>Kirim Umpan Balik</span>
                    </button>
                </div>
            </form>
            @else
                <div class="info-box">Umpan balik hanya dapat diberikan oleh pemilik submission.</div>
            @endif

            @else
            <div class="info-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>
                    <a href="{{ route('login') }}" style="color:var(--color-primary-light); text-decoration:none; font-weight:500;">Masuk ke akun</a>
                    untuk memberikan umpan balik dan membantu meningkatkan akurasi model.
                </span>
            </div>
            @endauth
        </div>
        @endif

        <!-- Action Buttons -->
        <div class="animate-fade-in-up delay-300" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <a href="{{ url('/') }}#cek-berita" class="btn-secondary" style="justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                Cek Berita Lain
            </a>
            @if($result)
                <a href="{{ route('hasil.pdf', $submission->id) }}" class="btn-ghost" style="border:1px solid var(--color-border-light); justify-content:center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Unduh PDF
                </a>
            @else
                <button onclick="window.print()" class="btn-ghost" style="border:1px solid var(--color-border-light); justify-content:center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Cetak / Simpan PDF
                </button>
            @endif
        </div>
        @auth
            <div style="text-align:center; margin-top:1rem;">
                <a href="{{ route('riwayat.csv') }}" style="color:var(--color-text-muted); font-size:0.8125rem; text-decoration:underline;">Unduh Riwayat CSV</a>
            </div>
        @endauth

        <!-- Disclaimer -->
        <div class="warning-box" style="margin-top:1.5rem;" role="note">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>
                Hasil ini bersifat <strong>indikatif probabilistik</strong>. Sistem kami tidak memberikan keputusan hukum atau jaminan mutlak. Selalu verifikasi ke sumber terpercaya:
                <a href="https://www.kominfo.go.id" target="_blank" rel="noopener noreferrer" style="color:inherit; font-weight:500;">Kominfo</a>,
                <a href="https://www.mafindo.or.id" target="_blank" rel="noopener noreferrer" style="color:inherit; font-weight:500;">Mafindo</a>,
                <a href="https://cekfakta.com" target="_blank" rel="noopener noreferrer" style="color:inherit; font-weight:500;">CekFakta</a>.
            </span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Animate confidence bar on load
document.addEventListener('DOMContentLoaded', () => {
    const fill = document.getElementById('confidence-fill');
    if (fill) {
        setTimeout(() => {
            fill.style.width = fill.closest('[role="progressbar"]')?.getAttribute('aria-valuenow') + '%' || '82%';
        }, 300);
    }
});

// Toggle extracted text
function toggleExtracted() {
    const panel = document.getElementById('extracted-text-panel');
    const icon = document.getElementById('toggle-icon');
    const btn = document.getElementById('toggle-extracted');
    const isOpen = panel.style.display !== 'none';
    panel.style.display = isOpen ? 'none' : 'block';
    icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
    btn.setAttribute('aria-expanded', !isOpen);
}

// Feedback selection
function selectFeedback(isCorrect) {
    document.getElementById('feedback-is-correct').value = isCorrect ? '1' : '0';
    const btnCorrect = document.getElementById('fb-correct');
    const btnWrong = document.getElementById('fb-wrong');
    const commentArea = document.getElementById('feedback-comment-area');

    if (isCorrect) {
        btnCorrect.style.background = 'rgba(16,185,129,0.15)';
        btnCorrect.style.borderColor = 'rgba(16,185,129,0.3)';
        btnCorrect.style.color = '#34d399';
        btnCorrect.setAttribute('aria-pressed', 'true');
        btnWrong.style.background = 'transparent';
        btnWrong.style.borderColor = 'rgba(99,102,241,0.4)';
        btnWrong.style.color = 'var(--color-primary-light)';
        btnWrong.setAttribute('aria-pressed', 'false');
        commentArea.style.display = 'block';
    } else {
        btnWrong.style.background = 'rgba(239,68,68,0.15)';
        btnWrong.style.borderColor = 'rgba(239,68,68,0.3)';
        btnWrong.style.color = '#f87171';
        btnWrong.setAttribute('aria-pressed', 'true');
        btnCorrect.style.background = 'transparent';
        btnCorrect.style.borderColor = 'rgba(99,102,241,0.4)';
        btnCorrect.style.color = 'var(--color-primary-light)';
        btnCorrect.setAttribute('aria-pressed', 'false');
        commentArea.style.display = 'block';
        commentArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>
@endpush
