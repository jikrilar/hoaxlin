<section class="hl-result__section" aria-labelledby="provenance-heading">
    <h2 id="provenance-heading">Input dan provenance</h2>
    <x-ui.disclosure title="Input dan teks analisis" id="input-provenance">
        <h3>Input asli</h3>
        @if(filled($submission->raw_input))
            <p class="hl-result__text">{{ $submission->raw_input }}</p>
        @elseif(filled($submission->source_url))
            @php $inputUrl = \App\View\Presenters\DetectionPresentation::sourceUrl($submission->source_url); @endphp
            @if($inputUrl)
                <p><a class="hl-link" href="{{ $inputUrl }}" target="_blank" rel="noopener noreferrer">{{ $submission->source_url }}</a></p>
            @else
                <p class="hl-result__text">{{ $submission->source_url }}</p>
            @endif
        @elseif(! filled($submission->media_path))
            <p>Input asli tidak tersedia.</p>
        @endif
        @if(filled($submission->media_path))
            <p>Media dikirim secara privat. File dapat sudah tidak tersedia karena retensi penyimpanan.</p>
            @if($submission->input_type === 'image')
                <img class="hl-result__media" src="{{ route('hasil.media', $submission->id) }}" loading="lazy" alt="Gambar yang dikirim untuk analisis">
            @endif
            <a class="hl-button" data-variant="secondary" href="{{ route('hasil.media', $submission->id) }}" target="_blank" rel="noopener noreferrer">Lihat media privat</a>
        @endif
        @if(filled($submission->extracted_text))
            <h3>Teks hasil ekstraksi</h3>
            <p class="hl-result__hint">Teks yang diperoleh dari input untuk pemrosesan.</p>
            <p class="hl-result__text" id="extracted-text-panel">{{ $submission->extracted_text }}</p>
        @endif
        @if(filled($submission->translated_text))
            <h3>Terjemahan bahasa Indonesia untuk analisis</h3>
            <p class="hl-result__hint">Terjemahan digunakan untuk analisis dan berbeda dari input asli.</p>
            <p class="hl-result__text">{{ $submission->translated_text }}</p>
        @endif
    </x-ui.disclosure>
    <x-ui.disclosure title="Sumber data dan model" id="model-provenance">
        <dl class="hl-result__metadata hl-result__metadata--details">
            <div><dt>Bahasa sumber</dt><dd>{{ filled($submission->source_language) ? $submission->source_language : 'Tidak tersedia' }}</dd></div>
            <div><dt>Provider terjemahan</dt><dd>{{ filled($submission->translation_provider) ? $submission->translation_provider : 'Tidak tersedia' }}</dd></div>
            <div><dt>Model terjemahan</dt><dd>{{ filled($submission->translation_model) ? $submission->translation_model : 'Tidak tersedia' }}</dd></div>
            @if($submission->translation_cached !== null)
                <div><dt>Cache terjemahan</dt><dd>{{ $submission->translation_cached ? 'Digunakan' : 'Tidak digunakan' }}</dd></div>
            @endif
            @if($result)
                <div><dt>Versi classifier</dt><dd>{{ filled($result->model_version) ? $result->model_version : 'Tidak tersedia' }}</dd></div>
                <div><dt>Model penjelasan</dt><dd>{{ filled($result->explanation_model) ? $result->explanation_model : 'Tidak tersedia' }}</dd></div>
                @if($result->inference_ms !== null)
                    <div><dt>Waktu inferensi classifier</dt><dd>{{ $result->inference_ms }} ms</dd></div>
                @endif
                @if($result->classifier_cached !== null)
                    <div><dt>Cache classifier</dt><dd>{{ $result->classifier_cached ? 'Digunakan' : 'Tidak digunakan' }}</dd></div>
                @endif
                @if($result->explanation_cached !== null)
                    <div><dt>Cache penjelasan</dt><dd>{{ $result->explanation_cached ? 'Digunakan' : 'Tidak digunakan' }}</dd></div>
                @endif
            @endif
            <div><dt>Versi pipeline</dt><dd>{{ filled($submission->pipeline_version) ? $submission->pipeline_version : 'Tidak tersedia' }}</dd></div>
            @if($submission->processing_completed_at)
                <div><dt>Pemrosesan selesai</dt><dd><time datetime="{{ $submission->processing_completed_at->toIso8601String() }}">{{ $submission->processing_completed_at->format('d M Y, H:i T') }}</time></dd></div>
            @endif
        </dl>
    </x-ui.disclosure>
</section>
