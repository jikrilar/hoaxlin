@props(['variant' => 'compact', 'href' => '/cara-kerja#meaning-title'])

@php
    if (! in_array($variant, ['compact', 'full'], true)) {
        throw new InvalidArgumentException('Disclaimer variant must be compact or full.');
    }

    $hasSlot = trim((string) $slot) !== '';
@endphp

<aside {{ $attributes->class(['hl-disclaimer', 'hl-disclaimer--'.$variant]) }} data-disclaimer="{{ $variant }}">
    @if ($variant === 'compact')
        <p>Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
        @if ($hasSlot)
            <div class="hl-disclaimer__context">{{ $slot }}</div>
        @endif
    @else
        <p>Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif. Prediksi bisa keliru dan bukan keputusan akhir.</p>
        <p>Label <x-ui.term key="meragukan">belum terverifikasi</x-ui.term> bukan bukti bahwa informasi benar atau palsu. <x-ui.term key="confidence">Skor keyakinan model</x-ui.term> bukan persentase kebenaran berita.</p>
        @if ($hasSlot)
            <div class="hl-disclaimer__context">{{ $slot }}</div>
        @endif
        <p><x-ui.link :href="$href">Cara membaca hasil</x-ui.link></p>
    @endif
</aside>
