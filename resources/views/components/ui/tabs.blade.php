{{--
    Accessible tabs (T2.3).

    Server-rendered semantics are complete on their own: role=tablist/tab/tabpanel,
    aria-selected and a roving tabindex. `resources/js/components/tabs.js` adds
    click/arrow/Home/End switching. Without JS the panels stay visible, so the
    component degrades to labelled sections instead of hiding content.

    Panels are rendered by the caller through the `panel-<key>` named slots so page
    markup (forms, fieldsets, hidden inputs) never lives inside a component prop.
--}}
@props(['id', 'label', 'tabs', 'active' => null])

@php
    if (! is_string($id) || ! preg_match('/^[A-Za-z][\w-]*$/', $id) || ! is_string($label) || trim($label) === ''
        || ! is_array($tabs) || $tabs === []) {
        throw new InvalidArgumentException('Tabs require an id, an accessible label and at least one tab.');
    }

    $keys = [];
    foreach ($tabs as $tab) {
        if (! is_array($tab) || ! is_string($tab['key'] ?? null) || ! preg_match('/^[a-z][a-z0-9-]*$/', $tab['key'])
            || ! is_string($tab['label'] ?? null) || trim($tab['label']) === '') {
            throw new InvalidArgumentException('Each tab needs a lowercase key and a visible label.');
        }
        $keys[] = $tab['key'];
    }
    if (count(array_unique($keys)) !== count($keys)) {
        throw new InvalidArgumentException('Tab keys must be unique.');
    }

    $activeKey = $active ?? $keys[0];
    if (! in_array($activeKey, $keys, true)) {
        throw new InvalidArgumentException('The active tab must exist in the tab list.');
    }
@endphp

<div {{ $attributes->class(['hl-tabs']) }} data-tabs data-active-tab="{{ $activeKey }}">
    <div role="tablist" aria-label="{{ $label }}" class="hl-tabs__list">
        @foreach($tabs as $tab)
            @php $selected = $tab['key'] === $activeKey; @endphp
            <button type="button" role="tab" id="{{ $id }}-tab-{{ $tab['key'] }}"
                aria-controls="{{ $id }}-panel-{{ $tab['key'] }}" aria-selected="{{ $selected ? 'true' : 'false' }}"
                tabindex="{{ $selected ? '0' : '-1' }}" data-tab-key="{{ $tab['key'] }}"
                class="hl-tabs__tab">{{ $tab['label'] }}</button>
        @endforeach
    </div>
    @foreach($tabs as $tab)
        @php $selected = $tab['key'] === $activeKey; @endphp
        <div id="{{ $id }}-panel-{{ $tab['key'] }}" role="tabpanel"
            aria-labelledby="{{ $id }}-tab-{{ $tab['key'] }}" tabindex="0"
            class="hl-tabs__panel" @if(! $selected) hidden @endif>
            @includeIf('components.ui.tabs.'.$id.'-'.$tab['key'])
        </div>
    @endforeach
</div>
