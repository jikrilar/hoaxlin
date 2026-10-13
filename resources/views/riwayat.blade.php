@extends('layouts.app')

@section('title', 'Riwayat Pengecekan — hoaxlin.id')
@section('description', 'Lihat riwayat pengecekan berita yang pernah kamu lakukan.')

@section('content')
<div style="padding:9rem 0 5rem; min-height:100vh;">
    <div class="container-app" style="max-width:960px;">

        <!-- Page Header -->
        <div style="margin-bottom:2.5rem;">
            <h1 style="font-size:2rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.5rem;">
                Riwayat <span class="gradient-text">Pengecekan</span>
            </h1>
            <p style="color:var(--color-text-secondary);">
                Berikut daftar berita yang pernah kamu kirimkan untuk dianalisis.
            </p>
        </div>

        <!-- Filter & Search Bar -->
        <div class="glass-card" style="padding:1.25rem 1.5rem; margin-bottom:1.5rem;">
            <form method="GET" action="{{ route('riwayat') }}" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
                <!-- Search -->
                <div style="flex:1; min-width:0; flex-basis:200px; position:relative;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="position:absolute; left:1rem; top:50%; transform:translateY(-50%); color:var(--color-text-muted);" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="search" id="history-search" name="search" value="{{ request('search') }}" class="form-input" style="padding-left:2.75rem; height:2.5rem; font-size:0.875rem;"
                           placeholder="Cari teks atau URL..." aria-label="Cari riwayat">
                </div>

                <select name="input_type" class="form-input" style="width:auto; height:2.5rem; font-size:0.875rem; padding:0 1rem;" aria-label="Filter jenis input">
                    <option value="">Semua Jenis</option>
                    @foreach(['text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video', 'url' => 'URL'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('input_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="status" class="form-input" style="width:auto; height:2.5rem; font-size:0.875rem; padding:0 1rem;" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    @foreach(['pending' => 'Menunggu', 'processing' => 'Diproses', 'completed' => 'Selesai', 'failed' => 'Gagal'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <!-- Sort -->
                <select id="history-sort" name="sort" class="form-input"
                        style="width:auto; height:2.5rem; font-size:0.875rem; padding:0 1rem; cursor:pointer;"
                         aria-label="Urutkan riwayat">
                    <option value="newest" @selected(request('sort', 'newest') === 'newest')>Terbaru</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Terlama</option>
                    <option value="confidence" @selected(request('sort') === 'confidence')>Confidence Tertinggi</option>
                </select>

                <button type="submit" class="btn-primary" style="height:2.5rem; padding:0 1rem;">Terapkan</button>
                @if(request()->hasAny(['search', 'label', 'input_type', 'status', 'sort']))
                    <a href="{{ route('riwayat') }}" class="btn-ghost" style="height:2.5rem;">Reset</a>
                @endif
            </form>

            <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:1rem;" role="group" aria-label="Filter berdasarkan label">
                @php $queryWithoutLabel = request()->except(['label', 'page']); @endphp
                @foreach(['' => 'Semua Hasil', 'valid' => '✅ Valid', 'hoax' => '🚨 Hoax', 'meragukan' => '⚠️ Meragukan'] as $value => $label)
                    @php $isActive = request('label', '') === $value; @endphp
                    <a href="{{ route('riwayat', $value === '' ? $queryWithoutLabel : [...$queryWithoutLabel, 'label' => $value]) }}"
                      style="padding:0.375rem 0.875rem; border-radius:2rem; border:1px solid {{ $isActive ? 'var(--ds-bg-selected)' : 'var(--color-border)' }}; background:{{ $isActive ? 'var(--ds-bg-selected)' : 'transparent' }}; color:{{ $isActive ? 'var(--color-primary-light)' : 'var(--color-text-muted)' }}; font-size:0.8125rem; font-weight:500; text-decoration:none;">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- History Table/Cards -->
        @if(isset($submissions) && count($submissions) > 0)
        <div class="glass-card">
            <!-- Table header -->
            <div class="history-header" style="padding:1rem 1.5rem; border-bottom:1px solid var(--color-border); " role="row" aria-label="Header tabel">
                <span style="color:var(--color-text-muted); font-size:0.8125rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Input</span>
                <span style="color:var(--color-text-muted); font-size:0.8125rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Jenis</span>
                <span style="color:var(--color-text-muted); font-size:0.8125rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Hasil</span>
                <span style="color:var(--color-text-muted); font-size:0.8125rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Tanggal</span>
                <span style="color:var(--color-text-muted); font-size:0.8125rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;"></span>
            </div>

            <!-- Rows -->
            <div id="history-list" role="list">
                @foreach($submissions as $submission)
                @php
                    $result = $submission->isCompleted() ? $submission->detectionResult : null;
                    $label = $result ? strtolower($result->label) : null;
                    $labelMap = ['valid'=>'Valid','hoax'=>'Hoax','meragukan'=>'Meragukan'];
                    $icons = ['valid'=>'✅','hoax'=>'🚨','meragukan'=>'⚠️'];
                    $statusMap = ['pending'=>'Menunggu','processing'=>'Diproses','completed'=>'Selesai','failed'=>'Gagal'];
                    $typeMap = ['text'=>'Teks','image'=>'Gambar','video'=>'Video','url'=>'URL'];
                    $typeIcons = ['text'=>'🔤','image'=>'🖼️','video'=>'🎬','url'=>'🔗'];
                @endphp
                <div class="history-row" role="listitem"
                    style="padding:1rem 1.5rem; ">

                    <!-- Input Preview -->
                    <div>
                        <p style="color:var(--color-text-primary); font-size:0.875rem; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:300px;">
                            {{ Str::limit($submission->raw_input ?? $submission->source_url ?? 'Input Media', 70) }}
                        </p>
                    </div>

                    <!-- Type -->
                    <div>
                        <span style="font-size:0.8125rem; color:var(--color-text-muted);">
                            {{ $typeIcons[$submission->input_type] ?? '📄' }}
                            {{ $typeMap[$submission->input_type] ?? $submission->input_type }}
                        </span>
                    </div>

                    <!-- Label -->
                    <div>
                        @if($result)
                            <span class="result-badge {{ $label }}" style="font-size:0.75rem; padding:0.25rem 0.75rem;">
                                {{ $icons[$label] ?? '❓' }} {{ $labelMap[$label] ?? $label }}
                            </span>
                            @if($result?->model_version)
                                <div style="font-size:0.625rem; color:var(--color-text-muted); margin-top:0.25rem;">v{{ $result->model_version }} · p{{ config('services.openai.prompt_version', '1.0') }}</div>
                            @endif
                        @else
                            <span style="font-size:0.75rem; color:{{ $submission->status === 'failed' ? 'var(--ds-danger-fg)' : 'var(--ds-warning-fg)' }};">
                                {{ $statusMap[$submission->status] ?? ucfirst($submission->status) }}
                            </span>
                        @endif
                    </div>

                    <!-- Date -->
                    <div>
                        <p style="color:var(--color-text-muted); font-size:0.8125rem;">{{ $submission->created_at->format('d M Y') }}</p>
                        <p style="color:var(--color-text-muted); font-size:0.75rem;">{{ $submission->created_at->format('H:i') }}</p>
                    </div>

                    <!-- Action -->
                    <div>
                        <a href="{{ route('riwayat.show', $submission) }}" class="btn-ghost" style="font-size:0.8125rem; padding:0.375rem 0.75rem;">
                            Detail
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Pagination -->
        @if($submissions->hasPages())
        <div style="margin-top:1.5rem; display:flex; justify-content:center;" aria-label="Navigasi halaman">
            {{ $submissions->links() }}
        </div>
        @endif

        @else
        <x-ui.empty-state
            :title="request()->hasAny(['search', 'label', 'input_type', 'status']) ? 'Riwayat Tidak Ditemukan' : 'Belum Ada Riwayat'"
            :message="request()->hasAny(['search', 'label', 'input_type', 'status']) ? 'Coba ubah kata kunci atau filter yang digunakan.' : 'Kamu belum pernah mengecek berita. Mulai cek berita pertamamu sekarang!'">
            <x-slot:actions>
                <x-ui.link :href="url('/').'#cek-berita'" variant="primary">Cek Berita Sekarang</x-ui.link>
            </x-slot:actions>
        </x-ui.empty-state>
        @endif

        <!-- Stats Summary -->
        @if(isset($submissions) && count($submissions) > 0)
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:1rem; margin-top:1.5rem;"  aria-label="Ringkasan statistik">
            @php
                $totalValid = $stats['valid'];
                $totalHoax = $stats['hoax'];
                $totalMeragukan = $stats['meragukan'];
            @endphp
            <div class="stat-card">
                <div class="stat-value" style="color:var(--ds-success-fg); -webkit-text-fill-color:var(--ds-success-fg); background:none;" aria-label="{{ $totalValid }} valid">{{ $totalValid }}</div>
                <div class="stat-label">Berita Valid</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--ds-danger-fg); -webkit-text-fill-color:var(--ds-danger-fg); background:none;" aria-label="{{ $totalHoax }} hoax">{{ $totalHoax }}</div>
                <div class="stat-label">Hoax Terdeteksi</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--ds-warning-fg); -webkit-text-fill-color:var(--ds-warning-fg); background:none;" aria-label="{{ $totalMeragukan }} meragukan">{{ $totalMeragukan }}</div>
                <div class="stat-label">Meragukan</div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
