@php
    $retryHeader = isset($exception) && $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
        ? ($exception->getHeaders()['Retry-After'] ?? null) : null;
    $retrySeconds = is_int($retryHeader) || is_string($retryHeader)
        ? filter_var($retryHeader, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
@endphp
@if($retrySeconds !== false)
    <p class="hl-error-hint">Server meminta jeda {{ $retrySeconds }} detik sebelum permintaan berikutnya.</p>
@endif
