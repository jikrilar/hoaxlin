<div
    wire:key="submission-progress-{{ $submission->id }}"
    role="region"
    aria-label="Progress pemrosesan"
>
    <div
        @if(! $isCompleted && ! $isFailed)
            wire:poll.2s.visible="refreshProgress"
        @endif
    >
    {{-- Progress Bar --}}
    <div style="width:4rem; height:4rem; margin:0 auto 1.25rem; border-radius:1rem; background:var(--ds-bg-selected); display:flex; align-items:center; justify-content:center; font-size:1.75rem;" aria-hidden="true">
        @if($isFailed)
            ❌
        @elseif($isCompleted)
            ✅
        @else
            ⏳
        @endif
    </div>

    <p style="color:var(--color-primary-light); font-size:0.8125rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; margin-bottom:0.75rem;">
        Status: <span data-stage-label>{{ $isFailed ? 'Gagal' : ($isCompleted ? 'Selesai' : $stageLabel) }}</span>
    </p>

    <h1 style="font-size:1.5rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.75rem;">
        {{ $isFailed ? 'Pemrosesan Gagal' : ($isCompleted ? 'Analisis Selesai' : 'Sedang Menganalisis') }}
    </h1>

    <p style="color:var(--color-text-muted); line-height:1.7; max-width:560px; margin:0 auto 1.5rem;">
        @if($isFailed)
            Terjadi kendala saat memproses input. Silakan coba kirim ulang atau hubungi administrator jika masalah berlanjut.
        @elseif($isCompleted)
            Analisis telah selesai. Hasil deteksi akan ditampilkan di bawah.
        @else
            Input kamu sedang diproses oleh sistem AI. Tahapan saat ini: <strong style="color:var(--color-text-secondary);" data-stage-label>{{ $stageLabel }}</strong> — halaman ini akan diperbarui otomatis.
        @endif
    </p>

    {{-- Accessible Progress Bar --}}
    <div style="max-width:480px; margin:0 auto 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
            <span style="color:var(--color-text-muted); font-size:0.75rem; font-weight:500;">Progress</span>
            <span style="color:var(--color-primary-light); font-size:0.8125rem; font-weight:var(--ds-font-weight-semibold);" aria-live="polite">{{ $progress }}%</span>
        </div>
        <div
            role="progressbar"
            aria-valuenow="{{ $progress }}"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="Progress pemrosesan {{ $progress }} persen, tahap {{ $stageLabel }}"
           style="width:100%; height:0.75rem; background:var(--ds-bg-selected); border-radius:9999px; overflow:hidden; border:1px solid var(--ds-border-control);"
        >
            <div
               style="height:100%; width:{{ $progress }}%; background:{{ $isFailed ? 'var(--ds-danger-fg)' : ($isCompleted ? 'var(--ds-success-fg)' : 'var(--ds-action-primary)') }}; border-radius:9999px; transition:width var(--ds-duration-progress) var(--ds-ease); will-change:width;"
            ></div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:0.5rem; gap:0.25rem;">
            @php
                $stages = [
                    ['label' => 'Antrean', 'pct' => 0],
                    ['label' => 'Ekstraksi', 'pct' => 25],
                    ['label' => 'Klasifikasi', 'pct' => 60],
                    ['label' => 'Penjelasan', 'pct' => 85],
                    ['label' => 'Selesai', 'pct' => 100],
                ];
            @endphp
            @foreach($stages as $s)
                <span style="font-size:0.625rem; font-weight:500; color:{{ $progress >= $s['pct'] ? 'var(--color-primary-light)' : 'var(--color-text-muted)' }}; text-align:center; flex:1;">
                    {{ $s['label'] }}
                </span>
            @endforeach
        </div>
    </div>

    @if($isCompleted)
        <p style="margin-top:1.25rem;">
            <a href="{{ route('hasil', $submission->id) }}" class="btn-primary" style="display:inline-flex;" wire:navigate>
                Lihat Hasil
            </a>
        </p>
        <script>
            // Auto-reload once when completed to show the full result (if still on polling view)
            if (! window.__submissionCompletedReloaded) {
                window.__submissionCompletedReloaded = true;
                setTimeout(() => window.location.reload(), 800);
            }
        </script>
    @endif
    </div>
</div>
