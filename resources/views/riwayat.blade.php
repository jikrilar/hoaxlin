@extends('layouts.app')

@section('title', 'Riwayat pemeriksaan — hoaxlin.id')
@section('description', 'Temukan pemeriksaan sebelumnya, gunakan filter riwayat akun, dan buka hasilnya.')

@php
    $labelOptions = ['' => 'Semua label', 'valid' => 'Valid (prediksi model)', 'hoax' => 'Hoaks (prediksi model)', 'meragukan' => 'Informasi belum terverifikasi oleh sumber terpercaya'];
    $typeOptions = ['' => 'Semua jenis', 'text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video', 'url' => 'Tautan artikel'];
    $statusOptions = ['' => 'Semua status', 'pending' => 'Menunggu', 'processing' => 'Diproses', 'completed' => 'Selesai', 'failed' => 'Gagal'];
    $sortOptions = ['newest' => 'Terbaru', 'oldest' => 'Terlama', 'confidence' => 'Skor keyakinan model tertinggi'];
    $filterValues = [];
    foreach (['search', 'label', 'input_type', 'status', 'sort'] as $field) {
        $value = old($field, request($field, $field === 'sort' ? 'newest' : ''));
        $filterValues[$field] = is_string($value) ? $value : '';
    }
    $hasFilters = false;
    $activeFilterCount = 0;
    foreach (request()->only(['search', 'label', 'input_type', 'status']) as $value) {
        if (is_string($value) && trim($value) !== '') { $hasFilters = true; $activeFilterCount++; }
    }
    $emptyPage = $submissions->isEmpty() && $submissions->total() > 0;
    $firstPageUrl = route('riwayat', request()->only(['search', 'label', 'input_type', 'status', 'sort']));
    $errorTargets = ['search' => 'history-search', 'label' => 'history-label', 'input_type' => 'history-input-type', 'status' => 'history-status', 'sort' => 'history-sort'];
@endphp

@section('content')
<div class="container-app hl-history-page">
    <header class="hl-history-intro">
        <h1>Riwayat pemeriksaan</h1>
        <p>Temukan pemeriksaan yang pernah Anda kirim dan buka hasilnya.</p>
        <div class="hl-history-actions">
            <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
            @if(auth()->user()?->hasVerifiedEmail())
                <x-ui.link :href="route('riwayat.csv')" variant="secondary">Unduh seluruh riwayat CSV</x-ui.link>
            @endif
        </div>
        <p class="hl-history-note">CSV berisi seluruh riwayat akun Anda, bukan hanya halaman atau filter yang sedang digunakan.</p>
    </header>

    <section class="hl-history-summary" aria-labelledby="history-summary-title">
        <h2 id="history-summary-title">Ringkasan seluruh riwayat</h2>
        <p class="hl-history-note">Ringkasan label yang tersimpan pada seluruh riwayat akun. Angka ini tidak mengikuti filter atau halaman aktif.</p>
        <ul class="hl-history-chips" role="list">
            <li class="hl-history-chip" data-tone="neutral"><x-ui.badge tone="success">Valid</x-ui.badge><span class="hl-history-chip__count">{{ $stats['valid'] }}</span></li>
            <li class="hl-history-chip" data-tone="danger"><x-ui.badge tone="danger">Hoaks</x-ui.badge><span class="hl-history-chip__count">{{ $stats['hoax'] }}</span></li>
            <li class="hl-history-chip" data-tone="warning"><x-ui.badge tone="warning">Belum terverifikasi</x-ui.badge><span class="hl-history-chip__count">{{ $stats['meragukan'] }}</span></li>
        </ul>
    </section>

    <section class="hl-history-filters" aria-labelledby="history-filter-title">
        <h2 id="history-filter-title">Cari dan filter riwayat</h2>
        @if($errors->any())
            <x-ui.alert tone="danger" title="Filter belum diterapkan" role="alert" id="history-errors" tabindex="-1" autofocus class="hl-history-errors">
                <ul>
                    @foreach($errors->messages() as $field => $messages)
                        @foreach($messages as $message)
                            <li>
                                @if(isset($errorTargets[$field]))
                                    <a href="#{{ $errorTargets[$field] }}">{{ $message }}</a>
                                @else
                                    {{ $message }}
                                @endif
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif
        <form method="GET" action="{{ route('riwayat') }}" id="history-filter-form">
            {{-- Native <details> keeps every control submittable; the summary states how many filters are active. --}}
            <details class="hl-disclosure hl-history-filter-disclosure" @if($errors->any()) open @endif>
                <summary class="hl-disclosure__summary">Filter{{ $activeFilterCount > 0 ? ' ('.$activeFilterCount.' aktif)' : '' }}</summary>
                <div class="hl-disclosure__content">
            <div class="hl-history-filter-fields">
                <x-ui.field id="history-search" label="Cari teks atau URL" hint="Cari pada teks, hasil ekstraksi, atau URL. Maksimal 100 karakter." :error="$errors->first('search')" class="hl-history-search">
                    <x-forms.input type="search" name="search" :value="$filterValues['search']" maxlength="100" />
                </x-ui.field>
                <x-ui.field id="history-label" label="Label model" :error="$errors->first('label')">
                    <x-forms.select name="label">
                        @foreach($labelOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filterValues['label'] === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </x-ui.field>
                <x-ui.field id="history-input-type" label="Jenis input" hint="Pilih Video untuk unggahan atau tautan video yang dikirim melalui formulir pemeriksaan." :error="$errors->first('input_type')">
                    <x-forms.select name="input_type">
                        @foreach($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filterValues['input_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </x-ui.field>
                <x-ui.field id="history-status" label="Status pemeriksaan" :error="$errors->first('status')">
                    <x-forms.select name="status">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filterValues['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </x-ui.field>
                <x-ui.field id="history-sort" label="Urutkan riwayat" :error="$errors->first('sort')">
                    <x-forms.select name="sort">
                        @foreach($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filterValues['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </x-ui.field>
            </div>
            <div class="hl-history-filter-actions">
                <x-ui.button type="submit">Terapkan filter</x-ui.button>
                <x-ui.link :href="route('riwayat')" variant="tertiary">Reset filter</x-ui.link>
            </div>
                </div>
            </details>
        </form>
    </section>

    <section class="hl-history-results" aria-labelledby="history-results-title">
        <h2 id="history-results-title">Daftar pemeriksaan</h2>
        <x-ui.disclaimer class="hl-history-note"><x-ui.term key="confidence">Skor keyakinan model</x-ui.term> menunjukkan tingkat keyakinan model, bukan persentase kebenaran berita. Informasi yang belum terverifikasi bukan bukti bahwa informasi benar atau palsu.</x-ui.disclaimer>
        @if($submissions->isNotEmpty())
            <p class="hl-history-note">Menampilkan {{ $submissions->firstItem() }}–{{ $submissions->lastItem() }} dari {{ $submissions->total() }} pemeriksaan{{ $hasFilters ? ' yang sesuai filter' : '' }}.</p>
            <ul class="hl-history-list" id="history-list">
                @foreach($submissions as $submission)
                    @php
                        $result = \App\View\Presenters\DetectionPresentation::finalResult($submission, $submission->detectionResult);
                        $prediction = $result ? \App\View\Presenters\DetectionPresentation::label($result->label) : null;
                        $confidence = $result ? \App\View\Presenters\DetectionPresentation::confidence($result->confidence_score) : null;
                        $preview = filled($submission->raw_input) ? $submission->raw_input : (filled($submission->source_url) ? $submission->source_url : 'Input media');
                    @endphp
                    <li class="hl-card hl-history-item" data-history-id="{{ $submission->id }}">
                        <h3 class="hl-history-item__preview">{{ \Illuminate\Support\Str::limit($preview, 150) }}</h3>
                        <p class="hl-history-item__meta">
                            {{ \App\View\Presenters\DetectionPresentation::inputType($submission->input_type) }} ·
                            <time datetime="{{ $submission->created_at->toIso8601String() }}">{{ $submission->created_at->format('d M Y, H:i') }}</time>
                        </p>
                        <p class="hl-history-item__status">
                            <span>Status: <strong>{{ $statusOptions[$submission->status] ?? 'Status belum tersedia' }}</strong></span>
                            <span class="hl-history-item__score">Skor keyakinan model: <strong data-history-confidence>{{ $confidence ?? 'Belum tersedia' }}</strong></span>
                        </p>
                        <p class="hl-history-item__label">
                            {{-- Tone is reinforcement only: the label text itself states the meaning. --}}
                            <span class="hl-badge" data-history-label data-tone="{{ $prediction['tone'] ?? 'neutral' }}"><span class="hl-badge__text">{{ $prediction['text'] ?? 'Belum tersedia' }}</span></span>
                        </p>
                        <div class="hl-history-item__actions">
                            <x-ui.link :href="route('riwayat.show', $submission->id)" variant="secondary" :aria-label="'Lihat hasil pemeriksaan '.$submission->id">Lihat hasil</x-ui.link>
                        </div>
                    </li>
                @endforeach
            </ul>
            <x-ui.pagination :paginator="$submissions" label="Navigasi halaman riwayat" />
        @else
            <x-ui.empty-state
                :title="$emptyPage ? 'Halaman riwayat kosong' : ($hasFilters ? 'Tidak ada pemeriksaan yang sesuai filter' : 'Belum ada riwayat pemeriksaan')"
                :message="$emptyPage ? 'Halaman yang diminta tidak berisi pemeriksaan. Kembali ke halaman pertama.' : ($hasFilters ? 'Ubah kata kunci atau reset filter untuk melihat riwayat lainnya.' : 'Mulai pemeriksaan pertama Anda untuk menyimpan riwayat di akun ini.')">
                <x-slot:actions>
                    @if($emptyPage)
                        <x-ui.link :href="$firstPageUrl" variant="secondary">Ke halaman pertama</x-ui.link>
                    @elseif($hasFilters)
                        <x-ui.link :href="route('riwayat')" variant="secondary">Reset filter</x-ui.link>
                    @else
                        <x-ui.link :href="route('home').'#cek-berita'" variant="primary">Periksa informasi</x-ui.link>
                    @endif
                </x-slot:actions>
            </x-ui.empty-state>
        @endif
    </section>
</div>
@endsection
