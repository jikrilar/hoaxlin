@php
    $flashMessages = [
        ['key' => 'success', 'tone' => 'success', 'title' => 'Berhasil', 'role' => 'status'],
        ['key' => 'error', 'tone' => 'danger', 'title' => 'Terjadi kesalahan', 'role' => 'alert'],
        ['key' => 'status', 'tone' => 'info', 'title' => 'Informasi', 'role' => 'status'],
    ];
@endphp

@if(session('success') || session('error') || session('status'))
    <section class="container-app hl-flash-list" aria-label="Pesan sistem">
        @foreach($flashMessages as $flash)
            @if(session($flash['key']))
                <x-ui.alert :tone="$flash['tone']" :role="$flash['role']" :title="$flash['title']" class="hl-flash" data-flash>
                    <div class="hl-flash__content">
                        <p class="hl-flash__message">{{ $flash['key'] === 'status' && session('status') === 'verification-link-sent' ? 'Tautan verifikasi baru telah dikirim. Periksa kotak masuk email Anda.' : session($flash['key']) }}</p>
                        <x-ui.button variant="tertiary" data-dismiss-flash :aria-label="'Tutup pesan: '.$flash['title']" hidden>Tutup</x-ui.button>
                    </div>
                </x-ui.alert>
            @endif
        @endforeach
    </section>
@endif
