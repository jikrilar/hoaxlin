<x-filament-widgets::widget class="hl-admin-budget">
    <x-filament::section heading="Biaya OpenAI bulan ini" :description="'Periode '.$budget['period'].' · kuota dari konfigurasi layanan'">
        <p class="hl-admin-note">Angka = biaya terpakai + reservasi berjalan dari pencatatan internal aplikasi, bukan tagihan resmi OpenAI.</p>
        @if(! $budget['available'] || $budget['spent_usd'] === null)
            <p class="hl-admin-note" role="status">Data biaya belum tersedia. Muat ulang halaman untuk mencoba membaca data kembali.</p>
        @else
            <p class="hl-admin-budget__amount" data-admin-openai-spend>
                <strong>${{ number_format($budget['spent_usd'], 2, '.', ',') }}</strong>
                <span>dari ${{ number_format($budget['quota_usd'], 2, '.', ',') }} kuota bulanan</span>
            </p>
            <div class="hl-admin-budget__bar" role="progressbar"
                aria-valuenow="{{ (int) round(($budget['ratio'] ?? 0) * 100) }}" aria-valuemin="0" aria-valuemax="100"
                aria-label="Pemakaian kuota OpenAI bulan ini"
                aria-valuetext="{{ (int) round(($budget['ratio'] ?? 0) * 100) }} persen dari kuota">
                <span style="inline-size: {{ (int) round(($budget['ratio'] ?? 0) * 100) }}%"></span>
            </div>
            <p class="hl-admin-note">Angka mencakup biaya terpakai dan reservasi berjalan. Kuota mencegah pemakaian berlebih; ini bukan target kualitas.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
