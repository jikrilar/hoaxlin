@props(['reference'])
@php $sourceUrl = \App\View\Presenters\DetectionPresentation::sourceUrl($reference->source_url); @endphp
<li class="hl-result-evidence__item" data-evidence-document="{{ $reference->document_id }}">
    <h3>{{ filled($reference->title) ? $reference->title : 'Judul rujukan tidak tersedia' }}</h3>
    <p class="hl-result__hint">
        <strong>Sumber:</strong> {{ filled($reference->source) ? $reference->source : 'Sumber tidak tersedia' }}<br>
        <strong>Tanggal publikasi:</strong> {{ $reference->published_at?->format('d M Y') ?? 'Tanggal publikasi tidak tersedia' }}
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
    @if(filled($reference->knowledge_base_version))
        <p class="hl-result__hint">Versi basis pengetahuan: {{ $reference->knowledge_base_version }}</p>
    @endif
</li>
