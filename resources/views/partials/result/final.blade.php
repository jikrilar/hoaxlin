<section class="hl-result__summary" aria-labelledby="result-heading" data-result-final>
    <h1 id="result-heading">Hasil analisis</h1>
    @include('partials.result.timestamps')
    <x-results.prediction :label="$result->label" :confidence="$result->confidence_score" />
</section>
<x-results.explanation :result="$result" />
<section class="hl-result__section hl-result-evidence" aria-labelledby="evidence-heading">
    <h2 id="evidence-heading">Referensi terkait</h2>
    <p class="hl-result__hint">Referensi berasal dari basis pengetahuan yang digunakan saat analisis. Keterkaitan sumber tidak otomatis mendukung prediksi model.</p>
    @php $references = \App\View\Presenters\DetectionPresentation::evidence($submission->evidenceReferences); @endphp
    @if($references->isEmpty())
        <x-ui.empty-state message="Tidak ditemukan referensi yang cukup relevan pada basis pengetahuan saat ini." />
    @else
        <ol class="hl-result-evidence__list">
            @foreach($references as $reference)
                <x-results.evidence-item :reference="$reference" />
            @endforeach
        </ol>
    @endif
</section>
