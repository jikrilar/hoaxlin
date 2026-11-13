@php
    $events = $getRecord()->processingEvents()->orderBy('created_at')->orderBy('id')->get();
@endphp

@if($events->isEmpty())
    <p class="hl-admin-note" role="status">Belum ada catatan tahap untuk pemeriksaan ini.</p>
@else
    <ol class="hl-admin-timeline" role="list" data-admin-timeline>
        @foreach($events as $event)
            <li class="hl-admin-timeline__item" data-stage="{{ $event->getRawOriginal('stage') }}" data-outcome="{{ $event->getRawOriginal('outcome') }}">
                <p class="hl-admin-timeline__heading">
                    <strong>{{ \App\Filament\Support\MonitoringPresentation::stage($event->getRawOriginal('stage')) }}</strong>
                    <x-filament::badge :color="match ($event->getRawOriginal('outcome')) {
                        'succeeded' => 'success',
                        'failed' => 'danger',
                        'retried', 'degraded' => 'warning',
                        default => 'gray',
                    }">{{ \App\Filament\Support\MonitoringPresentation::outcome($event->getRawOriginal('outcome')) }}</x-filament::badge>
                </p>
                <dl class="hl-admin-timeline__facts">
                    <div><dt>Layanan</dt><dd>{{ \App\Filament\Support\MonitoringPresentation::service($event->service) }}</dd></div>
                    <div><dt>Percobaan</dt><dd>{{ $event->attempt }}</dd></div>
                    <div><dt>Durasi</dt><dd>{{ \App\Filament\Support\MonitoringPresentation::duration($event->duration_ms) ?? 'Durasi tidak tercatat' }}</dd></div>
                    <div><dt>Kode kendala</dt><dd>{{ $event->error_code === null ? 'Kode kendala tidak tercatat' : \App\Filament\Support\MonitoringPresentation::errorCode($event->error_code) }}</dd></div>
                    <div><dt>Dicatat</dt><dd>{{ $event->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</dd></div>
                </dl>
                <pre class="hl-admin-timeline__metadata" data-monitoring-metadata>{{ \App\Filament\Support\MonitoringPresentation::eventMetadata($event) }}</pre>
            </li>
        @endforeach
    </ol>
@endif
