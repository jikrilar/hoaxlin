<section class="hl-result__section" aria-labelledby="provenance-heading">
    <h2 id="provenance-heading">Input dan <x-ui.term key="provenance">asal data</x-ui.term></h2>
    <x-ui.disclosure title="Input dan teks analisis" id="input-provenance">
        <dl class="hl-result__metadata">
            <div><dt>Bahasa terdeteksi</dt><dd>{{ filled($submission->source_language) ? strtoupper($submission->source_language) : 'Belum tersedia' }}</dd></div>
            <div><dt>Diterjemahkan</dt><dd>{{ filled($submission->translated_text) ? 'Ya, ke bahasa Indonesia' : 'Tidak' }}</dd></div>
        </dl>
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
</section>
