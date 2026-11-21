<x-filament-widgets::widget class="hl-admin-latency">
    <x-filament::section heading="Latensi analisis" :description="'Waktu inferensi model · '.$days.' hari'">
        @if($latency['p50'] === null)
            <p class="hl-admin-note" data-admin-latency-empty>
                Sampel belum cukup untuk persentil: {{ $latency['sample'] }} dari minimum {{ $latency['minimum'] }} hasil.
                Angka tidak ditampilkan agar beberapa pemeriksaan tidak terbaca sebagai tren.
            </p>
        @else
            <dl class="hl-admin-facts" data-admin-latency>
                <div><dt>p50 <x-admin.definition tooltip="Median waktu inferensi: separuh hasil selesai lebih cepat dari nilai ini." /></dt><dd><strong>{{ number_format($latency['p50'], 0, ',', '.') }} ms</strong><span>Median waktu inferensi</span></dd></div>
                <div><dt>p95 <x-admin.definition tooltip="Persentil ke-95: 95% hasil selesai di bawah nilai ini. Nearest-rank, bukan interpolasi." /></dt><dd><strong>{{ number_format($latency['p95'], 0, ',', '.') }} ms</strong><span>95% hasil berada di bawah nilai ini</span></dd></div>
                <div><dt>Sampel <x-admin.definition tooltip="Hanya hasil dengan waktu inferensi tercatat. Di bawah batas minimum, persentil tidak ditampilkan." /></dt><dd><strong>{{ number_format($latency['sample'], 0, ',', '.') }}</strong><span>Hasil dengan waktu inferensi tercatat</span></dd></div>
            </dl>
        @endif
        <p class="hl-admin-note">Latensi mengukur kecepatan layanan, bukan kualitas prediksi.</p>
    </x-filament::section>
</x-filament-widgets::widget>
