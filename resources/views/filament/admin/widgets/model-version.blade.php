<x-filament-widgets::widget :attributes="(new \Filament\Support\View\ComponentAttributeBag)->merge(['wire:poll.'.$pollingInterval.'.visible' => $pollingInterval ? true : null], escape: false)" class="hl-admin-model">
    <x-filament::section heading="Model dan data" description="Metadata dari runtime BERT /version. Riwayat hasil tidak menentukan model yang aktif.">
        <dl class="hl-admin-facts">
            @foreach($stats as $stat)
                <div>
                    <dt>{{ $stat->getLabel() }}</dt>
                    <dd><strong>{{ $stat->getValue() }}</strong><span>{{ $stat->getDescription() }}</span></dd>
                </div>
            @endforeach
        </dl>
        <p class="hl-admin-note">Akurasi evaluasi berlaku pada dataset, split, dan versi artefak yang tercantum; bukan jaminan akurasi produk pada informasi baru.</p>

        <h3 class="hl-admin-subheading">Data Kurasi terverifikasi</h3>
        @if(! $curation['available'])
            <p class="hl-admin-note" role="status">Jumlah Data Kurasi belum tersedia.</p>
        @else
            <table class="hl-admin-data-table" data-admin-curation>
                <caption>Baris Data Kurasi yang sudah diverifikasi admin · {{ number_format($curation['curated_total'], 0, ',', '.') }} baris</caption>
                <thead><tr><th scope="col">Label</th><th scope="col">Terverifikasi</th></tr></thead>
                <tbody>
                    @foreach(['valid', 'hoax', 'meragukan'] as $label)
                        <tr data-label="{{ $label }}"><th scope="row">{{ \App\View\Presenters\DetectionPresentation::label($label)['text'] }}</th><td>{{ number_format((int) ($curation['curated'][$label] ?? 0), 0, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <p class="hl-admin-note">Hanya baris berlabel valid atau hoaks yang layak dipakai melatih model; jumlah ini belum berarti model sudah dilatih ulang.</p>
        @endif
        <details class="hl-admin-data-disclosure" wire:ignore.self>
            <summary>Tiga kelompok versi dengan hasil tersimpan terbanyak</summary>
            <p class="hl-admin-note">Seluruh riwayat hasil, termasuk hasil nonfinal. Maksimal tiga kelompok; bukan jumlah seluruh versi model.</p>
            @if(!$history['available'])
                <p class="hl-admin-note">Riwayat versi belum tersedia.</p>
            @elseif($history['rows'] === [])
                <p class="hl-admin-note">Belum ada riwayat versi.</p>
            @else
                <table class="hl-admin-data-table" data-admin-versions>
                    <caption>Versi pada hasil tersimpan · seluruh riwayat</caption>
                    <thead><tr><th scope="col">Versi tercatat</th><th scope="col">Jumlah hasil</th></tr></thead>
                    <tbody>
                        @foreach($history['rows'] as $row)
                            <tr><th scope="row">{{ $row['version'] }}</th><td>{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </details>
    </x-filament::section>
</x-filament-widgets::widget>
