<x-filament-widgets::widget class="hl-admin-needs-action">
    <x-filament::section heading="Perlu tindakan" description="Pemeriksaan gagal dan yang tertahan lebih lama dari ambang pemulihan otomatis.">
        @if(! $action['available'])
            <p class="hl-admin-note" role="status">Data tindakan belum tersedia. Muat ulang halaman untuk mencoba membaca data kembali.</p>
        @else
            <dl class="hl-admin-facts" data-admin-needs-action>
                <div>
                    <dt>Pemeriksaan gagal <x-admin.definition tooltip="Seluruh riwayat pemeriksaan berstatus gagal, tanpa batas jendela waktu." /></dt>
                    <dd><strong>{{ number_format($action['failed'], 0, ',', '.') }}</strong><span>Status gagal, seluruh riwayat</span></dd>
                </div>
                <div>
                    <dt>Tugas latar belakang gagal <x-admin.definition tooltip="Tugas antrean yang berhenti karena kendala teknis; satu tugas dapat mencakup banyak pemeriksaan." /></dt>
                    <dd><strong>{{ number_format($action['jobs'], 0, ',', '.') }}</strong><span>{{ \App\Filament\Support\AdminGlossary::explain('failed_jobs') }}</span></dd>
                </div>
                <div>
                    <dt>Tertahan <x-admin.definition tooltip="Pemeriksaan berstatus menunggu atau diproses yang belum berubah melewati ambang pemulihan otomatis." /></dt>
                    <dd><strong>{{ number_format($action['stale'], 0, ',', '.') }}</strong><span>Menunggu/diproses &gt; {{ $action['threshold_minutes'] }} menit, ambang yang sama dengan <code>submissions:recover-stale</code></span></dd>
                </div>
            </dl>

            @if($action['items'] === [])
                <p class="hl-admin-note">Tidak ada pemeriksaan yang perlu ditindak saat ini.</p>
            @else
                <table class="hl-admin-data-table" data-admin-action-items>
                    <caption>Contoh pemeriksaan terbaru yang perlu ditindak</caption>
                    <thead><tr><th scope="col">ID</th><th scope="col">Status</th><th scope="col">Tahap</th><th scope="col">Diperbarui</th></tr></thead>
                    <tbody>
                        @foreach($action['items'] as $item)
                            <tr data-submission-id="{{ $item['id'] }}">
                                <td><a href="{{ \App\Filament\Resources\Submissions\SubmissionResource::getUrl('view', ['record' => $item['id']]) }}">#{{ $item['id'] }}</a></td>
                                <td>{{ $item['stale'] ? 'Tertahan' : 'Gagal' }}</td>
                                <td>{{ $item['stage'] ? (\App\Enums\ProcessingStage::tryFrom($item['stage'])?->label() ?? 'Tidak dikenali') : 'Tidak tercatat' }}</td>
                                <td>{{ $item['updated_at'] ? \Illuminate\Support\Carbon::parse($item['updated_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : 'Tidak tercatat' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <p class="hl-admin-note">Tindakan "Coba lagi" tersedia di daftar Pemeriksaan. Widget ini tidak mengubah data apa pun.</p>
        @endif

        <h3 class="hl-admin-subheading">Status layanan</h3>
        <ul class="hl-admin-service-list" role="list" data-admin-service-health>
            @foreach($health as $key => $service)
                <li data-service="{{ $key }}" data-state="{{ $service['state'] }}">
                    <span class="hl-admin-service-name">{{ $service['service'] }}</span>
                    <x-filament::badge :color="match($service['state']) {
                        \App\Services\Admin\ServiceHealth::OK => 'success',
                        \App\Services\Admin\ServiceHealth::DEGRADED => 'warning',
                        default => 'gray',
                    }">{{ $service['label'] }}</x-filament::badge>
                    <span class="hl-admin-note">{{ $service['detail'] }}</span>
                </li>
            @endforeach
        </ul>
        <p class="hl-admin-note">Status dibaca dari readiness layanan dan konfigurasi. "Belum tersedia" berarti tidak dapat diperiksa, bukan sehat.</p>
    </x-filament::section>
</x-filament-widgets::widget>
