<nav class="hl-result-actions" aria-label="Tindakan hasil">
    <a class="hl-button" data-variant="primary" href="{{ route('home') }}#cek-berita">Periksa informasi lain</a>
    @if($result)
        <a class="hl-button" data-variant="secondary" href="{{ route('hasil.pdf', $submission->id) }}">Unduh PDF</a>
    @endif
    @if($verifiedViewer)
        <a class="hl-button" data-variant="secondary" href="{{ route('riwayat') }}">Kembali ke riwayat</a>
        <a class="hl-button" data-variant="tertiary" href="{{ route('riwayat.csv') }}">Unduh seluruh riwayat CSV</a>
    @endif
</nav>
@if($verifiedViewer)
    <p class="hl-result__hint">CSV berisi seluruh riwayat akun Anda, bukan hanya hasil ini atau filter yang sedang digunakan.</p>
@endif
