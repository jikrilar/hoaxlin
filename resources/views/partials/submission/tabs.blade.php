{{-- Five input panels inside one tablist; panels are includes so form state stays page-owned. --}}
<div class="hl-tabs" data-tabs data-active-tab="{{ $activeTabKey }}">
    <div role="tablist" aria-label="Jenis input" class="hl-tabs__list">
        @foreach($inputTabs as $tab)
            @php($selected = $tab['key'] === $activeTabKey)
            <button type="button" role="tab" id="input-tab-{{ $tab['key'] }}"
                aria-controls="input-panel-{{ $tab['key'] }}" aria-selected="{{ $selected ? 'true' : 'false' }}"
                tabindex="{{ $selected ? '0' : '-1' }}" data-tab-key="{{ $tab['key'] }}"
                class="hl-tabs__tab">{{ $tab['label'] }}</button>
        @endforeach
    </div>
    @foreach($inputTabs as $tab)
        @include('partials.submission.panel-'.$tab['key'], [
            'active' => $tab['key'] === $activeTabKey,
            'previousInputType' => $previousInputType ?? '',
            'previousSourceUrl' => $previousSourceUrl ?? '',
        ])
    @endforeach
</div>
