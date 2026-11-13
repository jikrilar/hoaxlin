@php($days = $this->days())
<x-filament-widgets::widget class="hl-admin-charts">
    <div class="hl-admin-view-switch" role="group" aria-label="Pilih tampilan grafik">
        @foreach($this->getViewModes() as $key => $label)
            <button type="button" wire:click="$set('viewMode', '{{ $key }}')"
                class="hl-admin-view-switch__item" data-view="{{ $key }}"
                @if($this->mode() === $key) aria-pressed="true" @else aria-pressed="false" @endif>{{ $label }}</button>
        @endforeach
    </div>

    {{-- The chart canvas is `wire:ignore`: without a key that changes with the
         chart type, Livewire keeps the first Alpine instance and every view
         would paint as the trend line. --}}
    <div wire:key="admin-chart-{{ $this->mode() }}-{{ $this->getType() }}">
        @include('filament-widgets::chart-widget')
    </div>
    <noscript><p class="hl-admin-note" data-admin-chart-nojs>Grafik memerlukan JavaScript. Tabel data berikut tetap dapat dibuka.</p></noscript>

    <details class="hl-admin-data-disclosure" wire:ignore.self>
        <summary>Lihat data tabel · {{ $this->getHeading() }}</summary>
        @include('filament.admin.widgets.dashboard-charts-table')
    </details>
</x-filament-widgets::widget>
