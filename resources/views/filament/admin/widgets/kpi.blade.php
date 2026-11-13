<x-filament-widgets::widget class="hl-admin-kpi">
    <x-filament::section heading="Kinerja pemeriksaan" :description="'Jendela '.$days.' hari terakhir · '.config('app.timezone')">
        @if(! $kpis['available'])
            <p class="hl-admin-note" role="status">Data kinerja belum tersedia. Muat ulang halaman untuk mencoba membaca data kembali.</p>
        @else
            <dl class="hl-admin-kpi-grid" data-admin-kpis>
                <div>
                    <dt>Pemeriksaan hari ini <x-admin.definition tooltip="Jumlah pemeriksaan yang dikirim sejak 00.00 pada zona waktu aplikasi." /></dt>
                    <dd>{{ number_format((int) $kpis['today'], 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>7 hari terakhir <x-admin.definition tooltip="Tujuh hari terakhir termasuk hari ini; semua status." /></dt>
                    <dd>{{ number_format((int) $kpis['week'], 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>Tingkat selesai <x-admin.definition tooltip="Pemeriksaan berstatus selesai dibagi seluruh pemeriksaan dalam jendela yang dipilih." /></dt>
                    <dd>{{ $kpis['completion_rate'] === null ? 'Belum tersedia' : number_format($kpis['completion_rate'] * 100, 1, ',', '.').'%' }}</dd>
                    <span>selesai ÷ {{ number_format((int) $kpis['completion_base'], 0, ',', '.') }} pemeriksaan dalam jendela</span>
                </div>
                <div>
                    <dt>Gagal <x-admin.definition tooltip="Pemeriksaan berstatus gagal dalam jendela yang dipilih; bukan jumlah tugas latar belakang." /></dt>
                    <dd>{{ number_format((int) $kpis['failed'], 0, ',', '.') }}</dd>
                    <span>status gagal dalam jendela</span>
                </div>
            </dl>

            @if(array_sum($kpis['sparkline']) > 0)
                <figure class="hl-admin-sparkline" data-admin-sparkline>
                    <figcaption>Pemeriksaan per hari · 7 hari terakhir</figcaption>
                    @php($max = max(1, max($kpis['sparkline'])))
                    <ol class="hl-admin-sparkline__bars" role="list">
                        @foreach($kpis['sparkline'] as $index => $value)
                            <li><span style="block-size: {{ max(4, (int) round($value / $max * 100)) }}%" aria-hidden="true"></span><span class="hl-visually-hidden">{{ $value }} pemeriksaan</span></li>
                        @endforeach
                    </ol>
                </figure>
            @else
                <p class="hl-admin-note">Belum ada pemeriksaan dalam 7 hari terakhir.</p>
            @endif
        @endif
        <p class="hl-admin-note">Angka ini menggambarkan aktivitas aplikasi, bukan prevalensi hoaks di masyarakat dan bukan akurasi model.</p>
    </x-filament::section>
</x-filament-widgets::widget>
