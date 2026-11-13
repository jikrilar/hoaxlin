{{-- Static next steps per label (T2.7). Deliberately no external links (Appendix E-4). --}}
<section class="hl-result__section hl-result-next-steps" aria-labelledby="next-steps-heading">
    <h2 id="next-steps-heading">Langkah selanjutnya</h2>
    <ul class="hl-result-next-steps__list">
        @foreach(\App\View\Presenters\DetectionPresentation::nextSteps($result->label) as $step)
            <li>{{ $step }}</li>
        @endforeach
    </ul>
</section>
