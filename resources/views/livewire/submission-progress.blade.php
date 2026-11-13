@php
    $stage = \App\Enums\ProcessingStage::tryFrom($submission->processing_stage ?? '');
    $knownStatus = in_array($status, ['pending', 'processing', 'completed', 'failed'], true);
    $displayProgress = max(0, min(100, $progress));
    $refreshUrl = request()->routeIs('riwayat.show')
        ? route('riwayat.show', $submission->id)
        : route('hasil', $submission->id);
    $stepStates = \App\Enums\ProcessingStage::stepStates($stage, $isCompleted, $isFailed);
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
        <x-ui.term key="pipeline">{{ $isFailed ? 'Tahap pemeriksaan terakhir:' : 'Tahap pemeriksaan:' }}</x-ui.term>
        <span data-stage-label>{{ $stage ? $stageLabel : ($isCompleted ? 'Selesai' : 'Belum tersedia') }}</span>.
    </p>

    <p class="hl-submission-progress__description">
        @if($isFailed)
            Pemrosesan terhenti. Hasil final belum tersedia. Kembali ke formulir untuk mengirim pemeriksaan baru.
        @elseif($isCompleted)
            Pemeriksaan telah selesai. Buka halaman hasil untuk melihat hasil yang tersedia.
        @elseif(! $knownStatus)
            Status terbaru belum dapat ditampilkan. Muat ulang halaman untuk memeriksa pembaruan.
        @else
            Informasi Anda sedang menunggu atau menjalani analisis. Hasil final tersedia setelah seluruh tahap selesai.
        @endif
    </p>

    {{-- Stepper mirrors the recorded stage only; no step advances on its own. --}}
    <ol class="hl-stepper hl-submission-progress__stepper" aria-label="Tahap pemeriksaan" data-progress-stepper>
        @foreach($stepStates as $step)
            <li data-step="{{ $step['key'] }}" data-status="{{ $step['status'] }}"
                @if($step['status'] === 'current') aria-current="step" @endif>
                <span class="hl-stepper__number" aria-hidden="true">{{ $loop->iteration }}.</span>
                <span class="hl-stepper__label">{{ $step['label'] }}</span>
                <span class="hl-visually-hidden">({{ match($step['status']) {
                    'done' => 'selesai',
                    'current' => 'sedang berjalan',
                    'error' => 'gagal',
                    default => 'belum dimulai',
                } }})</span>
            </li>
        @endforeach
    </ol>

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
        @if($isCompleted)
            <a href="{{ $refreshUrl }}" class="hl-button" data-variant="primary" data-progress-refresh>Lihat Hasil</a>
        @else
            {{-- Polling is automatic; the manual reload is a fallback, not the primary action. --}}
            <a href="{{ $refreshUrl }}" class="hl-button" data-variant="tertiary" data-progress-refresh>
                {{ $isFailed ? 'Lihat status akhir' : 'Muat ulang status' }}
            </a>
        @endif
        @if($isFailed)
            <a href="{{ url('/') }}#cek-berita" class="hl-button" data-variant="primary">Periksa informasi baru</a>
        @endif
    </div>

</section>
