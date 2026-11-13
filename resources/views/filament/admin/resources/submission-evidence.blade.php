@php
    $submission = $getRecord();
    $references = \App\View\Presenters\DetectionPresentation::evidence($submission->evidenceReferences);
@endphp

@if($references->isEmpty())
    <p class="hl-admin-note" role="status">Belum ada rujukan tersimpan untuk pemeriksaan ini.</p>
@else
    <ol class="hl-admin-evidence" role="list" data-admin-evidence>
        @foreach($references as $reference)
            @php($sourceUrl = \App\View\Presenters\DetectionPresentation::sourceUrl($reference->source_url))
            <li class="hl-admin-evidence__item" data-evidence-document="{{ $reference->document_id }}">
                <p class="hl-admin-evidence__title"><strong>{{ filled($reference->title) ? $reference->title : 'Judul rujukan tidak tersedia' }}</strong></p>
                <dl class="hl-admin-evidence__facts">
                    <div><dt>Peringkat</dt><dd>{{ $reference->rank }}</dd></div>
                    <div><dt>Sumber</dt><dd>{{ filled($reference->source) ? $reference->source : 'Sumber tidak tersedia' }}</dd></div>
                    <div><dt>Tanggal publikasi</dt><dd>{{ $reference->published_at?->format('d M Y') ?? 'Tanggal publikasi tidak tersedia' }}</dd></div>
                    <div><dt>Kemiripan</dt><dd>{{ is_numeric($reference->similarity_score) ? round((float) $reference->similarity_score * 100).'%' : 'Tidak tercatat' }}</dd></div>
                    <div><dt>ID dokumen</dt><dd>{{ filled($reference->document_id) ? $reference->document_id : 'Tidak tercatat' }}</dd></div>
                    <div><dt>Versi basis pengetahuan</dt><dd>{{ filled($reference->knowledge_base_version) ? $reference->knowledge_base_version : 'Tidak tercatat' }}</dd></div>
                </dl>
                <blockquote class="hl-admin-evidence__snippet">{{ filled($reference->snippet) ? $reference->snippet : 'Kutipan tidak tersedia.' }}</blockquote>
                <p class="hl-admin-note">
                    <strong>Tautan sumber:</strong>
                    @if($sourceUrl)
                        <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">{{ $sourceUrl }}</a>
                    @else
                        {{ filled($reference->source_url) ? $reference->source_url : 'Tautan sumber tidak tersedia.' }}
                        <span class="hl-admin-note">(tidak ditautkan karena bukan URL http/https yang aman)</span>
                    @endif
                </p>
            </li>
        @endforeach
    </ol>
@endif
