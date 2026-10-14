@props(['paginator', 'label' => 'Navigasi halaman'])

@if($paginator->hasPages())
    <nav {{ $attributes->class(['hl-pagination']) }} aria-label="{{ $label }}">
        <p>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</p>
        <div class="hl-pagination__actions">
            @if($paginator->onFirstPage())
                <span class="hl-pagination__disabled" aria-disabled="true">Halaman sebelumnya</span>
            @else
                <x-ui.link :href="$paginator->previousPageUrl()" variant="secondary" rel="prev">Halaman sebelumnya</x-ui.link>
            @endif
            @if($paginator->hasMorePages())
                <x-ui.link :href="$paginator->nextPageUrl()" variant="secondary" rel="next">Halaman berikutnya</x-ui.link>
            @else
                <span class="hl-pagination__disabled" aria-disabled="true">Halaman berikutnya</span>
            @endif
        </div>
    </nav>
@endif
