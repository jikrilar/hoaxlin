@php
    $footerLinks = [
        ['route' => 'cara-kerja', 'label' => 'Cara kerja'],
        ['route' => 'tentang', 'label' => 'Tentang'],
        ['route' => 'kebijakan-privasi', 'label' => 'Privasi'],
        ['route' => 'statistik', 'label' => 'Statistik'],
    ];
@endphp

<footer class="hl-footer">
    <div class="container-app hl-footer__inner">
        <div>
            <a href="{{ route('home') }}" class="hl-wordmark">hoaxlin.id</a>
            <p class="hl-footer__description">Analisis informasi dengan bantuan AI. Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.</p>
            <p class="hl-footer__credit">© {{ date('Y') }} hoaxlin.id · Tugas Akhir D3 Teknik Komputer, Muhamad Jikril Aryanda.</p>
        </div>
        <nav aria-label="Informasi">
            <ul class="hl-footer__links">
                @foreach($footerLinks as $link)
                    <li><a class="hl-link" href="{{ route($link['route']) }}" @if(request()->routeIs($link['route'])) aria-current="page" @endif>{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>
</footer>
