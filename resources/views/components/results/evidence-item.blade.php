@props(['reference'])
@php
    $sourceUrl = \App\View\Presenters\DetectionPresentation::sourceUrl($reference->source_url);
    $similarity = $reference->similarity_score;
    $similarityLabel = is_numeric($similarity) && (float) $similarity >= 0 && (float) $similarity <= 1
        ? round((float) $similarity * 100).'%'
        : null;
@endphp
<li class="hl-result-evidence__item" data-evidence-document="{{ $reference->document_id }}">
    <h3>{{ filled($reference->title) ? $reference->title : 'Judul rujukan tidak tersedia' }}</h3>
    <p class="hl-result__hint">
        <strong>Sumber:</strong> {{ filled($reference->source) ? $reference->source : 'Sumber tidak tersedia' }}<br>
        <strong>Tanggal publikasi:</strong> {{ $reference->published_at?->format('d M Y') ?? 'Tanggal publikasi tidak tersedia' }}
        @if($similarityLabel !== null)
            <br><strong>Kemiripan dengan teks Anda:</strong> {{ $similarityLabel }}
        @endif
    </p>
    <blockquote class="hl-result__text">{{ filled($reference->snippet) ? $reference->snippet : 'Kutipan tidak tersedia.' }}</blockquote>
    <p class="hl-result-evidence__url">
        <strong>Tautan sumber:</strong>
        @if($sourceUrl)
            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ $sourceUrl }}</a>
        @else
            Tautan sumber tidak tersedia.
        @endif
    </p>
    @if($sourceUrl)
        <x-ui.link :href="$sourceUrl" variant="secondary" target="_blank" rel="noopener noreferrer" class="hl-result-evidence__open">
            Buka sumber<span class="hl-visually-hidden">: {{ filled($reference->title) ? $reference->title : 'rujukan' }} (tab baru)</span>
        </x-ui.link>
    @endif
    @if(filled($reference->knowledge_base_version))
        <p class="hl-result__hint">Versi <x-ui.term key="knowledge_base">basis pengetahuan</x-ui.term>: {{ $reference->knowledge_base_version }}</p>
    @endif
</li>
