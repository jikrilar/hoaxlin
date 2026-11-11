<nav class="hl-result-actions" aria-label="Tindakan hasil">
    <a class="hl-button" data-variant="primary" href="{{ route('home') }}#cek-berita">Periksa informasi lain</a>
    @if($result)
        <a class="hl-button" data-variant="secondary" href="{{ route('hasil.pdf', $submission->id) }}">Unduh PDF</a>
    @endif
    @if($verifiedViewer)
        <a class="hl-button" data-variant="secondary" href="{{ route('riwayat') }}">Kembali ke riwayat</a>
    @endif
</nav>
