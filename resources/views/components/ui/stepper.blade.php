{{--
    Ordered progress steps (T2.3).
    Status comes from data, never from a timer: a step is `done` only when the
    caller says so, `current` is the single step matching `current`, an explicit
    `status` of `error` marks a failure, and everything else stays `todo`.
--}}
@props(['steps', 'current' => null, 'label' => 'Tahap pemeriksaan'])

@php
    if (! is_array($steps) || $steps === [] || ! is_string($label) || trim($label) === '') {
        throw new InvalidArgumentException('A stepper requires ordered steps and an accessible label.');
    }

    foreach ($steps as $step) {
        if (! is_array($step) || ! is_string($step['key'] ?? null) || trim($step['key']) === ''
            || ! is_string($step['label'] ?? null) || trim($step['label']) === ''
            || (isset($step['status']) && ! in_array($step['status'], ['error'], true))) {
            throw new InvalidArgumentException('Each step needs a unique key, a label, and an optional error status.');
        }
    }

    $keys = array_column($steps, 'key');
    if (count(array_unique($keys)) !== count($keys)) {
        throw new InvalidArgumentException('Step keys must be unique.');
    }
    if ($current !== null && ! in_array($current, $keys, true)) {
        throw new InvalidArgumentException('Current step must exist in the step list.');
    }
    $currentIndex = $current === null ? -1 : (int) array_search($current, $keys, true);
@endphp

<ol aria-label="{{ $label }}" data-stepper {{ $attributes }}>
    @foreach($steps as $index => $step)
        @php
            $status = ($step['status'] ?? null) === 'error' ? 'error' : ($index === $currentIndex ? 'current' : ($index < $currentIndex ? 'done' : 'todo'));
        @endphp
        <li data-step data-status="{{ $status }}" data-step-key="{{ $step['key'] }}"
            @if($status === 'current') aria-current="step" @endif>
            <span class="hl-stepper__label">{{ $step['label'] }}</span>
        </li>
    @endforeach
</ol>
