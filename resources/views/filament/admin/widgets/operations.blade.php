<x-filament-widgets::widget :attributes="(new \Filament\Support\View\ComponentAttributeBag)->merge(['wire:poll.'.$pollingInterval.'.visible' => $pollingInterval ? true : null], escape: false)" class="hl-admin-operations">
    <x-filament::section heading="Ringkasan operasi" description="Agregat seluruh pengguna dan tamu. Tidak memuat isi submission atau data akun.">
        @if($available)
            <dl class="hl-admin-facts">
                @foreach($stats as $stat)
                    <div>
                        <dt>{{ $stat->getLabel() }}</dt>
                        <dd><strong>{{ $stat->getValue() }}</strong><span>{{ $stat->getDescription() }}</span></dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="hl-admin-note" role="status">Ringkasan operasi belum tersedia (n/a). Muat ulang halaman untuk mencoba membaca data kembali.</p>
        @endif

        <table class="hl-admin-data-table" data-admin-labels>
            <caption>Label hasil tersimpan · seluruh riwayat, semua versi model</caption>
            <thead><tr><th scope="col">Label prediksi</th><th scope="col">Jumlah hasil</th></tr></thead>
            <tbody>
                @foreach($labels as $label)
                    <tr data-label="{{ $label['code'] }}"><th scope="row">{{ $label['label'] }}</th><td>{{ $label['count'] === null ? 'n/a' : number_format($label['count'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @if($available && array_sum(array_column($labels, 'count')) === 0)
            <p class="hl-admin-note">Belum ada hasil dengan label tersimpan.</p>
        @endif
        <p class="hl-admin-note">Distribusi memakai hasil tersimpan, termasuk hasil pada submission nonfinal. Ini bukan prevalensi hoaks atau ukuran akurasi model.</p>
        <p class="hl-admin-note">Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif. Abstention bukan bukti informasi benar atau palsu.</p>
    </x-filament::section>
</x-filament-widgets::widget>
