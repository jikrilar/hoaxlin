@extends('layouts.app')

@section('title', 'Riwayat pemeriksaan — hoaxlin.id')
@section('description', 'Temukan pemeriksaan sebelumnya, gunakan filter riwayat akun, dan buka hasilnya.')

@php
    $labelOptions = ['' => 'Semua label', 'valid' => 'Valid (prediksi model)', 'hoax' => 'Hoaks (prediksi model)', 'meragukan' => 'Informasi belum terverifikasi oleh sumber terpercaya'];
    $typeOptions = ['' => 'Semua jenis', 'text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video unggahan', 'url' => 'Tautan artikel'];
    $statusOptions = ['' => 'Semua status', 'pending' => 'Menunggu', 'processing' => 'Diproses', 'completed' => 'Selesai', 'failed' => 'Gagal'];
    $sortOptions = ['newest' => 'Terbaru', 'oldest' => 'Terlama', 'confidence' => 'Skor keyakinan model tertinggi'];
    $filterValues = [];
    foreach (['search', 'label', 'input_type', 'status', 'sort'] as $field) {
        $value = old($field, request($field, $field === 'sort' ? 'newest' : ''));
        $filterValues[$field] = is_string($value) ? $value : '';
    }
    $hasFilters = false;
    foreach (request()->only(['search', 'label', 'input_type', 'status']) as $value) {
        if (is_string($value) && trim($value) !== '') $hasFilters = true;
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
        <dl>
            <div><dt>Valid (label model)</dt><dd>{{ $stats['valid'] }}</dd></div>
            <div><dt>Hoaks (label model)</dt><dd>{{ $stats['hoax'] }}</dd></div>
            <div><dt>Informasi belum terverifikasi oleh sumber terpercaya</dt><dd>{{ $stats['meragukan'] }}</dd></div>
        </dl>
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
                <x-ui.field id="history-input-type" label="Jenis input" hint="Video unggahan tidak mencakup tautan video." :error="$errors->first('input_type')">
                    <x-forms.select name="input_type">
                        @foreach($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filterValues['input_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </x-forms.select>
                </x-ui.field>
                <x-ui.field id="history-status" label="Status pemrosesan" :error="$errors->first('status')">
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
        </form>
    </section>

    <section class="hl-history-results" aria-labelledby="history-results-title">
        <h2 id="history-results-title">Daftar pemeriksaan</h2>
        <p class="hl-history-note">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif. Skor menunjukkan keyakinan model, bukan persentase kebenaran berita. Informasi yang belum terverifikasi bukan bukti bahwa informasi benar atau palsu.</p>
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
                    <li class="hl-history-item" data-history-id="{{ $submission->id }}">
                        <h3>{{ \Illuminate\Support\Str::limit($preview, 150) }}</h3>
                        <p class="hl-history-item__meta">
                            {{ \App\View\Presenters\DetectionPresentation::inputType($submission->input_type) }} ·
                            <time datetime="{{ $submission->created_at->toIso8601String() }}">{{ $submission->created_at->format('d M Y, H:i') }}</time>
                        </p>
                        <dl class="hl-history-state">
                            <div><dt>Status pemrosesan</dt><dd>{{ $statusOptions[$submission->status] ?? 'Status belum tersedia' }}</dd></div>
                            <div><dt>Hasil</dt><dd data-history-label data-tone="{{ $prediction['tone'] ?? 'neutral' }}">{{ $prediction['text'] ?? 'Belum tersedia' }}</dd></div>
                            <div><dt>Skor keyakinan model</dt><dd data-history-confidence>{{ $confidence ?? 'Belum tersedia' }}</dd></div>
                        </dl>
                        <x-ui.link :href="route('riwayat.show', $submission->id)" variant="tertiary" :aria-label="'Lihat hasil pemeriksaan '.$submission->id">Lihat hasil</x-ui.link>
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
