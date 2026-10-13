@php
    $stage = \App\Enums\ProcessingStage::tryFrom($submission->processing_stage ?? '');
    $knownStatus = in_array($status, ['pending', 'processing', 'completed', 'failed'], true);
    $displayProgress = max(0, min(100, $progress));
    $refreshUrl = request()->routeIs('riwayat.show')
        ? route('riwayat.show', $submission->id)
        : route('hasil', $submission->id);
@endphp

<section class="hl-submission-progress" wire:key="submission-progress-{{ $submission->id }}"
    data-progress-state="{{ $knownStatus ? $status : 'unknown' }}"
    aria-labelledby="progress-title-{{ $submission->id }}"
    @if(! $isCompleted && ! $isFailed) wire:poll.2s.visible="refreshProgress" @endif>
    <h1 id="progress-title-{{ $submission->id }}">
        @if($isFailed)
            Pemrosesan Gagal
        @elseif($isCompleted)
            Analisis Selesai
        @elseif($status === 'pending')
            Menunggu pemrosesan
        @elseif($status === 'processing')
            Sedang Menganalisis
        @else
            Status pemrosesan belum tersedia
        @endif
    </h1>

    <p class="hl-submission-progress__status" role="status" aria-live="polite" aria-atomic="true">
        Status: <span data-progress-status>{{ $isFailed ? 'Gagal' : ($isCompleted ? 'Selesai' : ($status === 'pending' ? 'Menunggu' : ($status === 'processing' ? 'Diproses' : 'Belum tersedia'))) }}</span>.
        {{ $isFailed ? 'Tahap terakhir tercatat:' : 'Tahap:' }}
        <span data-stage-label>{{ $stage ? $stageLabel : ($isCompleted ? 'Selesai' : 'Belum tersedia') }}</span>.
    </p>

    <p class="hl-submission-progress__description">
        @if($isFailed)
            Pemrosesan terhenti. Hasil final belum tersedia. Kembali ke form untuk mengirim pemeriksaan baru.
        @elseif($isCompleted)
            Pemrosesan telah selesai. Buka halaman hasil untuk melihat hasil yang tersedia.
        @elseif(! $knownStatus)
            Status terbaru belum dapat dijelaskan. Muat ulang halaman untuk memeriksa pembaruan.
        @else
            Input sedang menunggu atau menjalani analisis. Hasil final tersedia setelah status pemrosesan selesai.
        @endif
    </p>

    @if(! $isFailed && $knownStatus)
        <div class="hl-submission-progress__meter">
            <div class="hl-submission-progress__metadata">
                <span>Indikator tahap</span>
                <span data-progress-percent>{{ $displayProgress }}%</span>
            </div>
            <div class="hl-submission-progress__track" role="progressbar"
                aria-valuenow="{{ $displayProgress }}" aria-valuemin="0" aria-valuemax="100"
                aria-label="Indikator tahap pemrosesan"
                aria-valuetext="{{ $displayProgress }} persen; {{ $stage ? $stageLabel : 'tahap belum tersedia' }}; status {{ $status }}"
                aria-describedby="progress-hint-{{ $submission->id }}">
                <div class="hl-submission-progress__fill" style="width: {{ $displayProgress }}%"></div>
            </div>
            <p class="hl-submission-progress__hint" id="progress-hint-{{ $submission->id }}">
                Persentase mengikuti tahap atau status yang tercatat, bukan perkiraan waktu tersisa.
                <span data-progress-stage-unavailable @if($stage || $isCompleted) hidden @endif>Rincian tahap belum tersedia.</span>
                <span data-progress-not-final-done @if($isCompleted || $stage !== \App\Enums\ProcessingStage::Done) hidden @endif>
                    Tahap tercatat selesai, tetapi status pemrosesan belum selesai; hasil belum final.
                </span>
            </p>
        </div>
    @endif

    <div class="hl-submission-progress__actions">
        <a href="{{ $refreshUrl }}" class="hl-button" data-variant="{{ $isCompleted ? 'primary' : 'secondary' }}" data-progress-refresh>
            {{ $isCompleted ? 'Lihat Hasil' : 'Muat ulang status' }}
        </a>
        @if($isFailed)
            <a href="{{ url('/') }}#cek-berita" class="hl-button" data-variant="primary">Periksa informasi baru</a>
        @endif
    </div>

</section>
