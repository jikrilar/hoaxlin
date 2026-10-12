@extends('layouts.app')

@section('title', 'Tentang — hoaxlin.id')
@section('description', 'hoaxlin.id adalah sistem deteksi hoax berbasis BERT yang dikembangkan sebagai Tugas Akhir D3 Teknik Komputer.')

@section('content')

<!-- Hero -->
<section style="padding:9rem 0 5rem; text-align:center; position:relative; " aria-label="Header halaman tentang">

    <div class="container-app" style="position:relative; z-index:1;">
        <div class="hero-badge" style="display:inline-flex; margin-bottom:1.25rem; justify-content:center;">
            <span class="hero-badge-dot" aria-hidden="true"></span>
            Tugas Akhir D3 Teknik Komputer
        </div>
        <h1 class="hl-heading-editorial" style="margin-bottom:1rem;">
            Tentang <span class="gradient-text">hoaxlin.id</span>
        </h1>
        <p style="color:var(--color-text-secondary); max-width:600px; margin:0 auto; font-size:1.0625rem; line-height:1.7;">
            Sebuah sistem pendeteksi berita hoax berbasis kecerdasan buatan yang dikembangkan untuk membantu masyarakat Indonesia melawan disinformasi digital.
        </p>
    </div>
</section>

<!-- About Content -->
<section style="padding:2rem 0 5rem;" aria-label="Tentang proyek">
    <div class="container-app" style="max-width:900px;">

        <!-- Mission Card -->
        <div class="glass-card" style="padding:clamp(var(--ds-space-4), 4vw, var(--ds-space-12)); margin-bottom:2rem; position:relative; overflow:hidden;" role="region" aria-label="Misi proyek">
            <div style="position:absolute; top:0; left:0; right:0; height:3px; background:var(--ds-action-primary);" aria-hidden="true"></div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap:3rem; align-items:center;">
                <div>
                    <h2 style="font-size:1.875rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:1.25rem;">
                        Misi <span class="gradient-text">Kami</span>
                    </h2>
                    <p style="color:var(--color-text-secondary); font-size:1rem; line-height:1.8; margin-bottom:1rem;">
                        Penyebaran berita hoax dan disinformasi di Indonesia meningkat pesat seiring masifnya penggunaan media sosial. hoaxlin.id hadir sebagai solusi berbasis AI yang dapat diakses siapa saja.
                    </p>
                    <p style="color:var(--color-text-secondary); font-size:1rem; line-height:1.8;">
                        Dengan memanfaatkan kekuatan model BERT (Bidirectional Encoder Representations from Transformers), sistem kami mampu menganalisis pola kebahasaan yang membedakan berita valid dan hoax dalam konteks bahasa Indonesia.
                    </p>
                </div>
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    @foreach([
                        ['num'=>'n/a', 'label'=>'Evaluasi model aktif', 'color'=>'var(--ds-link)'],
                        ['num'=>'4', 'label'=>'Jenis Input Didukung', 'color'=>'var(--ds-info-fg)'],
                        ['num'=>'2026', 'label'=>'Tahun Pengembangan', 'color'=>'var(--ds-info-fg)'],
                    ] as $stat)
                    <div style="padding:1.25rem; background:var(--ds-bg-surface); border:1px solid var(--color-border); border-radius:0.875rem; display:flex; align-items:center; gap:1rem;">
                        <div style="font-family:var(--font-display); font-size:1.875rem; font-weight:var(--ds-font-weight-semibold); color:{{ $stat['color'] }}; min-width:4rem;" aria-label="{{ $stat['num'] }}">{{ $stat['num'] }}</div>
                        <div style="color:var(--color-text-muted); font-size:0.875rem;">{{ $stat['label'] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Developer Card -->
        <div class="glass-card" style="padding:clamp(var(--ds-space-4), 4vw, var(--ds-space-10)); margin-bottom:2rem;" role="region" aria-label="Tentang pengembang">
            <h2 style="font-size:1.375rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:1.75rem; display:flex; align-items:center; gap:0.75rem;">
                <span aria-hidden="true">👨‍💻</span>
                Pengembang
            </h2>
            <div style="display:flex; gap:2rem; align-items:flex-start; flex-wrap:wrap;">
                <!-- Avatar -->
                <div style="width:6rem; height:6rem; border-radius:1.25rem; background:var(--ds-bg-selected); border:2px solid var(--ds-border-control); display:flex; align-items:center; justify-content:center; font-size:2.5rem; flex-shrink:0;" aria-label="Avatar pengembang" role="img">
                    👤
                </div>
                <div style="flex:1;">
                    <h3 style="font-size:1.25rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.25rem;">Abdan Dzul Ghaffar Razaq</h3>
                    <p style="color:var(--color-primary-light); font-size:0.9375rem; margin-bottom:0.75rem;">Mahasiswa D3 Teknik Komputer</p>
                    <p style="color:var(--color-text-secondary); font-size:0.9375rem; line-height:1.7; margin-bottom:1.25rem;">
                        Proyek ini merupakan Tugas Akhir Program Diploma III Teknik Komputer berjudul <em style="color:var(--color-text-primary);">"Rancang Bangun Sistem Pendeteksi Berita Hoax dengan Metode BERT untuk Analisis Teks Mendalam"</em>.
                    </p>
                    <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                        @foreach(['Laravel', 'Livewire', 'Filament', 'Python', 'BERT / IndoBERT', 'Hugging Face', 'MySQL'] as $tech)
                        <span style="padding:0.25rem 0.75rem; background:var(--ds-bg-selected); border:1px solid var(--ds-border-control); border-radius:2rem; color:var(--color-primary-light); font-size:0.8125rem;">
                            {{ $tech }}
                        </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Tech Stack -->
        <div class="glass-card" style="padding:clamp(var(--ds-space-4), 4vw, var(--ds-space-10)); margin-bottom:2rem;" role="region" aria-label="Teknologi yang digunakan">
            <h2 style="font-size:1.375rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:1.75rem; display:flex; align-items:center; gap:0.75rem;">
                <span aria-hidden="true">⚙️</span>
                Teknologi yang Digunakan
            </h2>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap:1rem;">
                @php
                $techs = [
                    ['icon'=>'🟠', 'name'=>'Laravel 12', 'desc'=>'Framework backend PHP', 'role'=>'Aplikasi Web'],
                    ['icon'=>'⚡', 'name'=>'Livewire 4', 'desc'=>'Reaktivitas frontend', 'role'=>'UI Reaktif'],
                    ['icon'=>'🌺', 'name'=>'Filament 5', 'desc'=>'Panel administrasi', 'role'=>'Admin Panel'],
                    ['icon'=>'🐍', 'name'=>'Python / FastAPI', 'desc'=>'Serving model BERT', 'role'=>'ML Service'],
                    ['icon'=>'🤗', 'name'=>'Hugging Face', 'desc'=>'Library Transformers', 'role'=>'Model BERT'],
                    ['icon'=>'🤖', 'name'=>'OpenAI API', 'desc'=>'OCR, transkripsi, narasi', 'role'=>'Layanan Pendukung'],
                    ['icon'=>'🐬', 'name'=>'MySQL', 'desc'=>'Basis data relasional', 'role'=>'Database'],
                    ['icon'=>'🎨', 'name'=>'Tailwind CSS', 'desc'=>'Framework styling', 'role'=>'Styling'],
                ];
                @endphp
                @foreach($techs as $tech)
                <div style="padding:1.25rem; background:var(--ds-bg-surface); border:1px solid var(--color-border); border-radius:0.875rem; transition:all 0.25s ease;"
                     onmouseover="this.style.borderColor='var(--ds-border-control)'; this.style.transform='translateY(-2px)'"
                     onmouseout="this.style.borderColor='var(--color-border)'; this.style.transform='translateY(0)'">
                    <div style="font-size:1.5rem; margin-bottom:0.5rem;" aria-hidden="true">{{ $tech['icon'] }}</div>
                    <div style="font-weight:600; font-size:0.9375rem; margin-bottom:0.25rem;">{{ $tech['name'] }}</div>
                    <div style="color:var(--color-text-muted); font-size:0.8125rem; margin-bottom:0.5rem;">{{ $tech['desc'] }}</div>
                    <div style="padding:0.2rem 0.5rem; background:var(--ds-bg-selected); border-radius:2rem; display:inline-block; color:var(--color-primary-light); font-size:0.75rem; font-weight:500;">{{ $tech['role'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="warning-box" role="note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:1px;" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <strong style="display:block; margin-bottom:0.375rem;">Catatan Penting</strong>
                Sistem ini memberikan <strong>indikasi probabilistik</strong> berdasarkan pola kebahasaan, bukan keputusan hukum atau jaminan mutlak kebenaran suatu berita.
                Model dioptimalkan untuk teks <strong>bahasa Indonesia</strong>. Akurasi OCR dan transkripsi turut mempengaruhi kualitas hasil akhir.
                Selalu verifikasi berita ke sumber terpercaya.
            </div>
        </div>

    </div>
</section>

@endsection
