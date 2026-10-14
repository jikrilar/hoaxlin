<dl class="hl-result__metadata">
    <div><dt>Jenis input</dt><dd>{{ \App\View\Presenters\DetectionPresentation::inputType($submission->input_type) }}</dd></div>
    <div><dt>Dikirim</dt><dd>
        @if($submission->created_at)
            <time datetime="{{ $submission->created_at->toIso8601String() }}">{{ $submission->created_at->format('d M Y, H:i T') }}</time>
        @else
            Tidak tersedia
        @endif
    </dd></div>
</dl>
